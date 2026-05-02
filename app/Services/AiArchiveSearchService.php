<?php

namespace App\Services;

use App\Models\Archive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiArchiveSearchService
{
    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 20;

    private const MAX_CANDIDATES = 50;

    private const FIELD_WEIGHTS = [
        'judul arsip' => 40,
        'catatan arsip' => 18,
        'tahun arsip' => 16,
        'OCR arsip' => 24,
        'judul event' => 20,
        'kategori' => 14,
        'subkategori' => 14,
        'label lokasi' => 18,
        'catatan lokasi' => 12,
        'nama lemari' => 10,
        'nomor lemari' => 8,
        'nomor rak' => 8,
        'nomor slot' => 8,
    ];

    public function search(string $question, ?int $limit = null): array
    {
        $resolvedLimit = max(1, min($limit ?? self::DEFAULT_LIMIT, self::MAX_LIMIT));
        $terms = $this->extractTerms($question);
        $candidates = $this->findCandidates($question, $terms);

        $ranked = $candidates
            ->map(fn (Archive $archive) => $this->scoreArchive($archive, $question, $terms))
            ->filter(fn (array $result) => $result['match_score'] > 0)
            ->sortByDesc('match_score')
            ->values();

        $archives = $ranked->take($resolvedLimit)->all();

        return [
            'question' => $question,
            'resolved_terms' => $terms,
            'limit' => $resolvedLimit,
            'total_matches' => $ranked->count(),
            'archives' => $archives,
            'suggested_answer' => $this->buildSuggestedAnswer($archives),
        ];
    }

    /**
     * @param  array<int, string>  $terms
     * @return Collection<int, Archive>
     */
    private function findCandidates(string $question, array $terms): Collection
    {
        $searchTerms = collect([$question, ...$terms])
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->unique()
            ->values();

        return Archive::query()
            ->with([
                'event:id,title',
                'category:id,name',
                'subcategory:id,name',
                'files:id,archive_id,file_name,file_size,file_type,file_url,created_at,updated_at',
                'ocrText:id,archive_id,extracted_text',
                'physicalLocation:id,archive_id,cabinet_id,rack_id,slot_number,label_code,notes,created_at,updated_at',
                'physicalLocation.cabinet:id,cabinet_number,name,created_at,updated_at',
                'physicalLocation.rack:id,cabinet_id,rack_number,capacity,used_capacity,created_at,updated_at',
            ])
            ->where(function (Builder $query) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $like = '%'.$term.'%';

                    $query->orWhere('archives.title', 'like', $like)
                        ->orWhere('archives.notes', 'like', $like)
                        ->orWhere('archives.year', 'like', $like)
                        ->orWhereHas('ocrText', fn (Builder $ocrQuery) => $ocrQuery->where('extracted_text', 'like', $like))
                        ->orWhereHas('event', fn (Builder $eventQuery) => $eventQuery->where('title', 'like', $like))
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', $like))
                        ->orWhereHas('subcategory', fn (Builder $subcategoryQuery) => $subcategoryQuery->where('name', 'like', $like))
                        ->orWhereHas('physicalLocation', function (Builder $locationQuery) use ($like) {
                            $locationQuery->where('label_code', 'like', $like)
                                ->orWhere('notes', 'like', $like)
                                ->orWhere('slot_number', 'like', $like)
                                ->orWhereHas('cabinet', function (Builder $cabinetQuery) use ($like) {
                                    $cabinetQuery->where('name', 'like', $like)
                                        ->orWhere('cabinet_number', 'like', $like);
                                })
                                ->orWhereHas('rack', fn (Builder $rackQuery) => $rackQuery->where('rack_number', 'like', $like));
                        });
                }
            })
            ->limit(self::MAX_CANDIDATES)
            ->get();
    }

    /**
     * @param  array<int, string>  $terms
     * @return array<string, mixed>
     */
    private function scoreArchive(Archive $archive, string $question, array $terms): array
    {
        $fields = [
            'judul arsip' => (string) $archive->title,
            'catatan arsip' => (string) ($archive->notes ?? ''),
            'tahun arsip' => (string) ($archive->year ?? ''),
            'OCR arsip' => (string) optional($archive->ocrText)->extracted_text,
            'judul event' => (string) optional($archive->event)->title,
            'kategori' => (string) optional($archive->category)->name,
            'subkategori' => (string) optional($archive->subcategory)->name,
            'label lokasi' => (string) optional($archive->physicalLocation)->label_code,
            'catatan lokasi' => (string) optional($archive->physicalLocation)->notes,
            'nama lemari' => (string) optional(optional($archive->physicalLocation)->cabinet)->name,
            'nomor lemari' => (string) optional(optional($archive->physicalLocation)->cabinet)->cabinet_number,
            'nomor rak' => (string) optional(optional($archive->physicalLocation)->rack)->rack_number,
            'nomor slot' => (string) optional($archive->physicalLocation)->slot_number,
        ];

        $normalizedQuestion = $this->normalizeText($question);
        $score = 0;
        $reasons = [];
        $ocrExcerpt = null;

        foreach ($fields as $label => $value) {
            if ($value === '') {
                continue;
            }

            $fieldScore = 0;
            $matchedTerms = [];
            $normalizedValue = $this->normalizeText($value);

            if ($normalizedQuestion !== '' && Str::contains($normalizedValue, $normalizedQuestion)) {
                $fieldScore += self::FIELD_WEIGHTS[$label] + 20;
                $matchedTerms[] = $question;
            }

            foreach ($terms as $term) {
                $normalizedTerm = $this->normalizeText($term);

                if ($normalizedTerm === '' || ! Str::contains($normalizedValue, $normalizedTerm)) {
                    continue;
                }

                $fieldScore += self::FIELD_WEIGHTS[$label];
                $matchedTerms[] = $term;

                if ($label === 'OCR arsip' && $ocrExcerpt === null) {
                    $ocrExcerpt = $this->makeExcerpt($value, $term);
                }
            }

            if ($fieldScore <= 0) {
                continue;
            }

            $score += $fieldScore;
            $matchedTerms = array_values(array_unique($matchedTerms));
            $reasons[] = [
                'field' => $label,
                'matched_terms' => $matchedTerms,
                'field_score' => $fieldScore,
                'reason' => $label.' cocok dengan keyword: '.implode(', ', $matchedTerms),
            ];
        }

        if ($ocrExcerpt === null) {
            $ocrExcerpt = $this->makeExcerpt((string) optional($archive->ocrText)->extracted_text, $question);
        }

        return [
            'archive_id' => $archive->id,
            'title' => $archive->title,
            'year' => $archive->year,
            'notes' => $archive->notes,
            'match_score' => $score,
            'match_reasons' => $reasons,
            'event' => $archive->event ? [
                'id' => $archive->event->id,
                'title' => $archive->event->title,
            ] : null,
            'category' => $archive->category ? [
                'id' => $archive->category->id,
                'name' => $archive->category->name,
            ] : null,
            'subcategory' => $archive->subcategory ? [
                'id' => $archive->subcategory->id,
                'name' => $archive->subcategory->name,
            ] : null,
            'physical_location' => $this->formatPhysicalLocation($archive),
            'file' => $archive->files ? [
                'file_name' => $archive->files->file_name,
                'file_type' => $archive->files->file_type,
                'file_size' => $archive->files->file_size,
                'file_url' => $archive->files->file_url,
                'created_at' => optional($archive->files->created_at)?->toISOString(),
                'updated_at' => optional($archive->files->updated_at)?->toISOString(),
            ] : null,
            'ocr_excerpt' => $ocrExcerpt,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractTerms(string $question): array
    {
        preg_match_all('/"([^"]+)"/u', $question, $doubleQuotedMatches);
        preg_match_all("/'([^']+)'/u", $question, $singleQuotedMatches);

        $quotedTerms = array_merge($doubleQuotedMatches[1] ?? [], $singleQuotedMatches[1] ?? []);
        $strippedQuestion = preg_replace('/["\']([^"\']+)["\']/', ' ', $question) ?? $question;
        $tokens = preg_split('/[^[:alnum:]]+/u', Str::lower($strippedQuestion)) ?: [];

        $stopwords = [
            'yang', 'dan', 'atau', 'untuk', 'dengan', 'dari', 'pada', 'ke', 'di', 'arsip',
            'cari', 'cariin', 'mencari', 'tolong', 'ingin', 'tahu', 'mana', 'lokasi', 'nya',
            'ada', 'berisi', 'tentang', 'sekalian', 'fisiknya', 'the', 'a', 'an', 'is', 'are',
            'to', 'of', 'in', 'on', 'for', 'where', 'show', 'find',
        ];

        return collect([...$quotedTerms, ...$tokens])
            ->map(fn ($term) => trim((string) $term))
            ->filter(fn ($term) => $term !== '' && mb_strlen($term) >= 2)
            ->reject(fn ($term) => in_array(Str::lower($term), $stopwords, true))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeText(?string $value): string
    {
        return Str::lower(trim((string) $value));
    }

    private function makeExcerpt(string $text, string $needle, int $radius = 90): ?string
    {
        $cleanText = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($cleanText === '') {
            return null;
        }

        $position = mb_stripos($cleanText, $needle);
        if ($position === false) {
            return Str::limit($cleanText, 220);
        }

        $start = max(0, $position - $radius);
        $length = mb_strlen($needle) + ($radius * 2);
        $excerpt = mb_substr($cleanText, $start, $length);

        return ($start > 0 ? '...' : '').$excerpt.(mb_strlen($cleanText) > ($start + $length) ? '...' : '');
    }

    private function formatPhysicalLocation(Archive $archive): ?array
    {
        $location = $archive->physicalLocation;

        if (! $location) {
            return null;
        }

        $cabinet = $location->cabinet;
        $rack = $location->rack;
        $summaryParts = array_filter([
            $location->label_code ? 'label '.$location->label_code : null,
            $cabinet ? 'lemari '.$cabinet->name.' (#'.$cabinet->cabinet_number.')' : null,
            $rack ? 'rak '.$rack->rack_number : null,
            $location->slot_number ? 'slot '.$location->slot_number : null,
        ]);

        return [
            'id' => $location->id,
            'archive_id' => $location->archive_id,
            'label_code' => $location->label_code,
            'slot_number' => $location->slot_number,
            'notes' => $location->notes,
            'location_summary' => implode(', ', $summaryParts),
            'cabinet' => $cabinet ? [
                'id' => $cabinet->id,
                'cabinet_number' => $cabinet->cabinet_number,
                'name' => $cabinet->name,
                'created_at' => optional($cabinet->created_at)?->toISOString(),
                'updated_at' => optional($cabinet->updated_at)?->toISOString(),
            ] : null,
            'rack' => $rack ? [
                'id' => $rack->id,
                'rack_number' => $rack->rack_number,
                'capacity' => $rack->capacity,
                'used_capacity' => $rack->used_capacity,
                'created_at' => optional($rack->created_at)?->toISOString(),
                'updated_at' => optional($rack->updated_at)?->toISOString(),
            ] : null,
            'created_at' => optional($location->created_at)?->toISOString(),
            'updated_at' => optional($location->updated_at)?->toISOString(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $archives
     */
    private function buildSuggestedAnswer(array $archives): string
    {
        if ($archives === []) {
            return 'Belum ada arsip yang cukup cocok dengan pertanyaan ini.';
        }

        $top = $archives[0];
        $locationSummary = data_get($top, 'physical_location.location_summary');

        if ($locationSummary) {
            return 'Arsip yang paling cocok adalah "'.$top['title'].'". Lokasi fisiknya: '.$locationSummary.'.';
        }

        return 'Arsip yang paling cocok adalah "'.$top['title'].'", tetapi arsip ini belum memiliki lokasi fisik.';
    }
}
