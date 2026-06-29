<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\Admin;

use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use IvanBaric\Blog\Actions\SavePostAction;
use IvanBaric\Blog\Data\ActionResult;
use IvanBaric\Blog\Livewire\Forms\PostFormState;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Support\TeamResolver;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

final class PostForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?Post $post = null;

    public string $locale = 'en';

    public PostFormState $form;

    public string $categorySearch = '';

    public string $tagSearch = '';

    public bool $schedulePublication = false;

    public ?string $publishedDate = null;

    public ?string $publishedTime = null;

    public function mount(?Post $post = null): void
    {
        corexis_authorize($post?->exists ? 'blog.update' : 'blog.create', $post?->exists ? $post : []);

        $this->locale = $this->currentLocaleCode();
        $this->form->initialize($this->locale);

        if ($post?->exists) {
            $post = $this->postForCurrentTeam((string) $post->uuid);
            $this->post = $post;
            abort_unless((int) $post->getAttribute('team_id') === $this->currentTeamId(), 404);
            $this->form->fillFromPost($post, $this->locale);
        }

        $this->syncPublishedFieldsFromForm();
    }

    public function save(SavePostAction $savePostAction): void
    {
        $this->syncFormPublishedAt();

        try {
            $data = $this->form->data($this->currentTeamId());
        } catch (ValidationException $exception) {
            $this->toastFromResult(ActionResult::failure(__('Provjerite obavezna polja i pokušajte ponovno.')));

            throw $exception;
        }

        $post = $this->post?->exists ? $this->postForCurrentTeam((string) $this->post->uuid) : null;
        $result = $savePostAction->handle(
            post: $post,
            data: $data,
            categoryId: $this->form->categoryUuids,
            tagIds: $this->form->tagUuids,
            locale: $this->locale,
        );

        if (! $result->successful) {
            foreach ($result->data?->messages() ?? [] as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            $this->toastFromResult($result);

            return;
        }

        $this->post = $result->data;
        $this->form->fillFromPost($this->post, $this->locale);
        $this->syncPublishedFieldsFromForm();

        $this->toastFromResult($result);
        $this->redirectRoute(config('blog.routes.admin_name_prefix', 'admin.blog.').'edit', ['post' => $this->post->uuid], navigate: true);
    }

    public function createCategory(): void
    {
        $category = $this->createTaxonomyItem('category', $this->categorySearch, __('Kategorije'), false);

        if (! $category->exists) {
            return;
        }

        $categoryUuid = (string) $category->uuid;

        if (! in_array($categoryUuid, $this->form->categoryUuids, true)) {
            $this->form->categoryUuids[] = $categoryUuid;
        }

        $this->categorySearch = '';
    }

    public function createTag(): void
    {
        $tag = $this->createTaxonomyItem('tags', $this->tagSearch, __('Oznake'), true);

        if (! $tag->exists) {
            return;
        }

        $tagUuid = (string) $tag->uuid;

        if (! in_array($tagUuid, $this->form->tagUuids, true)) {
            $this->form->tagUuids[] = $tagUuid;
        }

        $this->tagSearch = '';
    }

    public function removeFeaturedImage(): void
    {
        $this->form->removeFeaturedImage();
    }

    public function removeTag(string $tagUuid): void
    {
        $this->form->tagUuids = array_values(array_filter(
            $this->form->tagUuids,
            static fn (string $selectedTagUuid): bool => $selectedTagUuid !== $tagUuid,
        ));
    }

    public function updatedFormCategoryUuids(mixed $value = null): void
    {
        $this->categorySearch = '';
    }

    public function updatedFormTagUuids(mixed $value = null): void
    {
        $this->tagSearch = '';
    }

    public function updatedSchedulePublication(bool $value): void
    {
        if (! $value) {
            $this->publishedDate = null;
            $this->publishedTime = null;
            $this->form->published_at = null;

            return;
        }

        $now = now();
        $this->publishedDate ??= $now->format('Y-m-d');
        $this->publishedTime ??= $now->format('H:i');

        $this->syncFormPublishedAt();
    }

    public function updatedPublishedDate(mixed $value = null): void
    {
        $this->syncFormPublishedAt();
    }

    public function updatedPublishedTime(mixed $value = null): void
    {
        $this->syncFormPublishedAt();
    }

    #[Computed]
    public function categories(): Collection
    {
        return $this->taxonomyItems('category', $this->categorySearch, $this->form->categoryUuids);
    }

    #[Computed]
    public function tags(): Collection
    {
        return $this->taxonomyItems('tags', $this->tagSearch, $this->form->tagUuids);
    }

    #[Computed]
    public function selectedTags(): Collection
    {
        if ($this->form->tagUuids === []) {
            return collect();
        }

        return TaxonomyItem::query()
            ->whereIn('uuid', $this->form->tagUuids)
            ->ordered()
            ->get();
    }

    public function render()
    {
        return view('blog::livewire.admin.post-form')
            ->layout(config('blog.admin_ui.layout', 'layouts.app'), [
                'title' => $this->post?->exists ? __('Uredi objavu') : __('Nova objava'),
            ]);
    }

    public function featuredImageUrl(): ?string
    {
        if (! $this->form->featured_image) {
            return null;
        }

        if (str_starts_with($this->form->featured_image, 'http://') || str_starts_with($this->form->featured_image, 'https://')) {
            return $this->form->featured_image;
        }

        return Storage::disk('public')->url($this->form->featured_image);
    }

    private function createTaxonomyItem(string $type, string $name, string $taxonomyName, bool $multiple): TaxonomyItem
    {
        $name = trim($name);

        if ($name === '') {
            $this->addError($type === 'category' ? 'categorySearch' : 'tagSearch', __('Naziv je obavezan.'));

            return new TaxonomyItem;
        }

        $teamId = $this->currentTeamId();

        $taxonomy = Taxonomy::query()
            ->where('type', $type)
            ->where('slug', Str::slug($taxonomyName))
            ->when($teamId, fn ($query) => $query->where('team_id', $teamId))
            ->first();

        if (! $taxonomy) {
            $taxonomy = new Taxonomy([
                'type' => $type,
                'slug' => Str::slug($taxonomyName),
                'name' => $taxonomyName,
                'is_filterable' => true,
                'is_multiple' => $multiple,
            ]);
            $taxonomy->forceFill(['team_id' => $teamId])->save();
        }

        $item = TaxonomyItem::query()
            ->where('taxonomy_id', $taxonomy->id)
            ->where('slug', Str::slug($name))
            ->when($teamId, fn ($query) => $query->where('team_id', $teamId))
            ->first();

        if (! $item) {
            $item = new TaxonomyItem([
                'taxonomy_id' => $taxonomy->id,
                'slug' => Str::slug($name),
                'name' => $name,
            ]);
            $item->forceFill(['team_id' => $teamId])->save();
        }

        return $item;
    }

    /**
     * @param  array<int, string>  $selectedUuids
     */
    private function taxonomyItems(string $type, string $search, array $selectedUuids = []): Collection
    {
        $search = trim($search);

        $results = TaxonomyItem::query()
            ->forType($type)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->ordered()
            ->limit(20)
            ->get();

        $selectedUuids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $uuid): string => trim((string) $uuid),
            $selectedUuids,
        ))));

        if ($selectedUuids === []) {
            return $results;
        }

        $missingSelectedUuids = array_values(array_diff($selectedUuids, $results->pluck('uuid')->map(fn (mixed $uuid): string => (string) $uuid)->all()));

        if ($missingSelectedUuids === []) {
            return $results;
        }

        return TaxonomyItem::query()
            ->whereIn('uuid', $missingSelectedUuids)
            ->ordered()
            ->get()
            ->merge($results);
    }

    private function syncPublishedFieldsFromForm(): void
    {
        if (! $this->form->published_at) {
            $this->schedulePublication = false;
            $this->publishedDate = null;
            $this->publishedTime = null;

            return;
        }

        $publishedAt = Carbon::parse($this->form->published_at);

        $this->schedulePublication = true;
        $this->publishedDate = $publishedAt->format('Y-m-d');
        $this->publishedTime = $publishedAt->format('H:i');
    }

    private function syncFormPublishedAt(): void
    {
        if (! $this->schedulePublication) {
            $this->form->published_at = null;

            return;
        }

        if (! filled($this->publishedDate)) {
            $this->form->published_at = null;

            return;
        }

        $date = $this->normalizedPublishedDate();

        if ($date === null) {
            $this->form->published_at = null;

            return;
        }

        $time = filled($this->publishedTime) ? substr((string) $this->publishedTime, 0, 5) : '00:00';

        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '00:00';
        }

        $this->form->published_at = $date.'T'.$time;
    }

    private function normalizedPublishedDate(): ?string
    {
        $date = trim((string) $this->publishedDate);

        if ($date === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})\.?$/', $date, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function postForCurrentTeam(string $uuid): Post
    {
        $model = config('blog.models.post', Post::class);

        return $model::query()
            ->forTeam($this->currentTeamId())
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function currentTeamId(): ?int
    {
        return app(TeamResolver::class)->resolve();
    }

    private function currentLocaleCode(): string
    {
        return corexis_locale_code() ?: config('app.locale', 'en');
    }

    private function toastFromResult(ActionResult $result): void
    {
        Flux::toast(variant: $result->successful ? 'success' : 'danger', text: $result->message);
    }
}
