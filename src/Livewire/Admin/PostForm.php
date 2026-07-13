<?php

declare(strict_types=1);

namespace IvanBaric\Blog\Livewire\Admin;

use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use IvanBaric\Blog\Actions\ArchivePostAction;
use IvanBaric\Blog\Actions\DeletePostAction;
use IvanBaric\Blog\Actions\PublishPostAction;
use IvanBaric\Blog\Actions\SavePostAction;
use IvanBaric\Blog\Livewire\Forms\PostFormState;
use IvanBaric\Blog\Models\Post;
use IvanBaric\Blog\Support\BlogConfigResolver;
use IvanBaric\Blog\Support\BlogModels;
use IvanBaric\Blog\Support\RichTextSanitizer;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Taxonomy\Models\TaxonomyItem;
use IvanBaric\Taxonomy\Support\TaxonomyModels;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class PostForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?Post $post = null;

    #[Locked]
    public string $locale = 'en';

    public PostFormState $form;

    public string $categorySearch = '';

    public string $tagSearch = '';

    public bool $schedulePublication = false;

    public ?string $publishedDate = null;

    public ?string $publishedTime = null;

    #[Locked]
    public array $savedStateSnapshot = [];

    #[Locked]
    public ?string $lastSavedAt = null;

    #[Locked]
    public ?string $lastSavedBy = null;

    #[Locked]
    public ?string $deletingPostUuid = null;

    #[Locked]
    public ?string $restoringPostUuid = null;

    #[Locked]
    public ?string $archivingPostUuid = null;

    public function mount(?Post $post = null): void
    {
        corexis_authorize($post?->exists ? 'blog.update' : 'blog.create', $post?->exists ? $post : []);

        $this->locale = $this->currentLocaleCode();
        $this->form->initialize($this->locale);

        if ($post?->exists) {
            $post = $this->postForCurrentTeam((string) $post->uuid);
            $this->post = $post;
            $this->form->fillFromPost($post, $this->locale);
        }

        $this->syncPublishedFieldsFromForm();
        $this->captureSavedStateSnapshot();
    }

    public function save(SavePostAction $savePostAction): void
    {
        if ($this->post?->status === 'archived') {
            Flux::toast(variant: 'danger', text: __('Arhivirana objava je zaključana. Prvo je vratite u skicu.'));

            return;
        }

        if ($this->post?->exists && $this->form->status === 'archived') {
            $this->confirmArchive();

            return;
        }

        if ($this->post?->exists && ! $this->isDirty()) {
            Flux::toast(variant: 'info', text: __('Nema promjena za spremanje.'));

            return;
        }

        $this->persistPost($savePostAction, redirectAfterCreate: true);
    }

    public function confirmArchive(): void
    {
        if (! $this->post?->exists || $this->post->status === 'archived') {
            return;
        }

        $post = $this->postForCurrentTeam((string) $this->post->uuid);

        if ($post->status === 'archived') {
            Flux::toast(variant: 'danger', text: __('Objava je već arhivirana. Osvježite stranicu.'));

            return;
        }

        $this->archivingPostUuid = (string) $post->uuid;

        Flux::modal('post-detail-archive-confirm')->show();
    }

    public function cancelArchive(): void
    {
        $this->reset('archivingPostUuid');

        if ($this->post?->exists && $this->post->status !== 'archived') {
            $this->form->status = $this->postForCurrentTeam((string) $this->post->uuid)->status;
        }
    }

    public function archiveAndSave(SavePostAction $savePostAction, ArchivePostAction $archivePostAction): void
    {
        if (! $this->archivingPostUuid || ! $this->post?->exists) {
            return;
        }

        $post = $this->postForCurrentTeam($this->archivingPostUuid);

        if ($post->status === 'archived' || (string) $post->uuid !== (string) $this->post->uuid) {
            $this->reset('archivingPostUuid');
            Flux::modal('post-detail-archive-confirm')->close();
            Flux::toast(variant: 'danger', text: __('Status objave promijenjen je u međuvremenu. Osvježite stranicu i pokušajte ponovno.'));

            return;
        }

        $this->form->status = $post->status;

        if (! $this->persistPost($savePostAction, redirectAfterCreate: false, showSuccessToast: false)) {
            $this->form->status = 'archived';
            $this->reset('archivingPostUuid');
            Flux::modal('post-detail-archive-confirm')->close();

            return;
        }

        $result = $archivePostAction->handle($this->postForCurrentTeam((string) $post->uuid));

        if (! $result->success) {
            $this->reset('archivingPostUuid');
            Flux::modal('post-detail-archive-confirm')->close();
            $this->toastFromResult($result);

            return;
        }

        $this->reset('archivingPostUuid');
        Flux::modal('post-detail-archive-confirm')->close();
        $this->toastFromResult($result);
        $this->redirectRoute(
            config('blog.routes.admin_name_prefix', 'admin.blog.').'edit',
            ['post' => $post->uuid],
        );
    }

    public function autoSave(SavePostAction $savePostAction): void
    {
        if (! $this->post?->exists || $this->post->status === 'archived' || $this->form->status === 'archived' || ! $this->isDirty()) {
            return;
        }

        $this->persistPost(
            savePostAction: $savePostAction,
            redirectAfterCreate: false,
            successToastMessage: __('Automatsko spremanje podataka'),
            showValidationToast: false,
        );
    }

    public function isDirty(): bool
    {
        if ($this->post?->status === 'archived') {
            return false;
        }

        if (! $this->post?->exists) {
            return true;
        }

        $this->syncFormPublishedAt();

        return $this->currentStateSnapshot() !== $this->savedStateSnapshot;
    }

    public function confirmRestore(): void
    {
        if (! $this->post?->exists || $this->post->status !== 'archived') {
            return;
        }

        $post = $this->postForCurrentTeam((string) $this->post->uuid);

        if ($post->status !== 'archived') {
            Flux::toast(variant: 'danger', text: __('Status objave promijenjen je u međuvremenu. Osvježite stranicu i pokušajte ponovno.'));

            return;
        }

        $this->restoringPostUuid = (string) $post->uuid;

        Flux::modal('post-detail-restore-confirm')->show();
    }

    public function cancelRestore(): void
    {
        $this->reset('restoringPostUuid');
    }

    public function restoreFromArchive(PublishPostAction $publishPostAction): void
    {
        if (! $this->restoringPostUuid || ! $this->post?->exists) {
            return;
        }

        $post = $this->postForCurrentTeam($this->restoringPostUuid);

        if ($post->status !== 'archived' || (string) $post->uuid !== (string) $this->post->uuid) {
            $this->cancelRestore();
            Flux::modal('post-detail-restore-confirm')->close();
            Flux::toast(variant: 'danger', text: __('Status objave promijenjen je u međuvremenu. Osvježite stranicu i pokušajte ponovno.'));

            return;
        }

        $result = $publishPostAction->handle($post, false);

        if (! $result->success || ! $result->data instanceof Post) {
            $this->cancelRestore();
            Flux::modal('post-detail-restore-confirm')->close();
            $this->toastFromResult($result);

            return;
        }

        $uuid = (string) $result->data->uuid;

        $this->cancelRestore();
        Flux::modal('post-detail-restore-confirm')->close();
        $this->toastFromResult($result);
        $this->redirectRoute(
            config('blog.routes.admin_name_prefix', 'admin.blog.').'edit',
            ['post' => $uuid],
        );
    }

    public function confirmDelete(): void
    {
        if (! $this->post?->exists) {
            return;
        }

        $post = $this->postForCurrentTeam((string) $this->post->uuid);

        if ($post->status !== 'archived') {
            Flux::toast(variant: 'danger', text: __('Samo arhivirana objava može se izbrisati iz ovog prikaza.'));

            return;
        }

        $this->deletingPostUuid = (string) $post->uuid;

        Flux::modal('post-detail-delete-confirm')->show();
    }

    public function cancelDelete(): void
    {
        $this->reset('deletingPostUuid');
    }

    public function delete(DeletePostAction $deletePostAction): void
    {
        if (! $this->deletingPostUuid || ! $this->post?->exists) {
            return;
        }

        $post = $this->postForCurrentTeam($this->deletingPostUuid);

        if ($post->status !== 'archived' || (string) $post->uuid !== (string) $this->post->uuid) {
            $this->cancelDelete();
            Flux::modal('post-detail-delete-confirm')->close();
            Flux::toast(variant: 'danger', text: __('Status objave promijenjen je u međuvremenu. Osvježite stranicu i pokušajte ponovno.'));

            return;
        }

        $result = $deletePostAction->handle($post);

        if (! $result->success) {
            $this->cancelDelete();
            Flux::modal('post-detail-delete-confirm')->close();
            $this->toastFromResult($result);

            return;
        }

        $this->reset('deletingPostUuid');
        Flux::toast(
            heading: __('Objava izbrisana'),
            text: __('Objava je uspješno uklonjena iz administracije.'),
            variant: 'success',
        );
        $this->skipRender();
        $this->redirectRoute(config('blog.routes.admin_name_prefix', 'admin.blog.').'index', navigate: true);
    }

    #[On('gallery-attached')]
    #[On('gallery-detached')]
    public function refreshGallerySummary(string $collection = ''): void
    {
        if ($collection === 'images') {
            unset($this->gallerySummary);
        }
    }

    private function persistPost(
        SavePostAction $savePostAction,
        bool $redirectAfterCreate,
        ?string $successToastMessage = null,
        bool $showValidationToast = true,
        bool $showSuccessToast = true,
    ): bool {
        $this->syncFormPublishedAt();

        try {
            $data = $this->form->data();
        } catch (ValidationException $exception) {
            if ($showValidationToast) {
                $this->toastFromResult(ActionResult::error(corexis_validation_toast_message(
                    $exception,
                    __('Provjerite obavezna polja i pokušajte ponovno.'),
                )));
            }

            if (! $showValidationToast) {
                return false;
            }

            throw $exception;
        }

        $wasExistingPost = (bool) $this->post?->exists;
        $post = $this->post?->exists ? $this->postForCurrentTeam((string) $this->post->uuid) : null;
        $result = $savePostAction->handle(
            post: $post,
            data: $data,
            categoryId: $this->form->categoryUuids,
            tagIds: $this->form->tagUuids,
            locale: $this->locale,
        );

        if (! $result->success) {
            foreach ($result->errors as $field => $messages) {
                $this->addError($this->formErrorKey((string) $field), $messages[0] ?? $result->message);
            }

            $this->toastFromResult($result);

            return false;
        }

        $this->post = $result->data;
        $this->form->fillFromPost($this->post, $this->locale);
        $this->syncPublishedFieldsFromForm();
        $this->captureSavedStateSnapshot();

        if ($showSuccessToast) {
            $successToastMessage === null
                ? $this->toastFromResult($result)
                : Flux::toast(variant: 'info', text: $successToastMessage);
        }

        if ($redirectAfterCreate && ! $wasExistingPost) {
            $this->redirectRoute(config('blog.routes.admin_name_prefix', 'admin.blog.').'edit', ['post' => $this->post->uuid], navigate: true);
        }

        return true;
    }

    private function formErrorKey(string $field): string
    {
        return match ($field) {
            'title' => 'form.title.'.$this->locale,
            'content' => 'form.content.'.$this->locale,
            'status' => 'form.status',
            'featuredImageUpload' => 'form.featuredImageUpload',
            default => str_starts_with($field, 'form.') ? $field : 'form.'.$field,
        };
    }

    public function createCategory(): void
    {
        if ($this->post?->status === 'archived') {
            return;
        }

        corexis_authorize($this->post?->exists ? 'blog.update' : 'blog.create', $this->post?->exists ? $this->post : []);

        $category = $this->createTaxonomyItem('category', $this->categorySearch, __('Kategorije'), false);

        if (! $category->exists) {
            return;
        }

        $categoryUuid = (string) $category->getAttribute('uuid');

        if (! in_array($categoryUuid, $this->form->categoryUuids, true)) {
            $this->form->categoryUuids[] = $categoryUuid;
        }

        $this->categorySearch = '';
    }

    public function createTag(): void
    {
        if ($this->post?->status === 'archived') {
            return;
        }

        corexis_authorize($this->post?->exists ? 'blog.update' : 'blog.create', $this->post?->exists ? $this->post : []);

        $tag = $this->createTaxonomyItem('tags', $this->tagSearch, __('Oznake'), true);

        if (! $tag->exists) {
            return;
        }

        $tagUuid = (string) $tag->getAttribute('uuid');

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

        $taxonomyItemModel = TaxonomyModels::taxonomyItem();

        return $taxonomyItemModel::query()
            ->whereIn('uuid', $this->form->tagUuids)
            ->when(config('corexis.tenancy.enabled', false), fn ($query) => $query->where((string) config('corexis.tenancy.id_column', 'team_id'), $this->currentTeamId()))
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
        if ($this->form->removeFeaturedImage) {
            return null;
        }

        return $this->post?->exists
            ? $this->post->featuredImageUrl('large')
            : null;
    }

    /** @return array{attached: bool, title: string|null, count: int} */
    #[Computed]
    public function gallerySummary(): array
    {
        if (! $this->post?->exists) {
            return ['attached' => false, 'title' => null, 'count' => 0];
        }

        $gallery = $this->postForCurrentTeam((string) $this->post->uuid)->gallery('images');

        if (! $gallery) {
            return ['attached' => false, 'title' => null, 'count' => 0];
        }

        return [
            'attached' => true,
            'title' => $gallery->displayTitle(),
            'count' => $gallery->media()->count(),
        ];
    }

    public function archivedContentHtml(): string
    {
        if ($this->post?->status !== 'archived') {
            return '';
        }

        $content = $this->post->localized('content');

        return app(RichTextSanitizer::class)->sanitize($content);
    }

    public function publicPostUrl(): ?string
    {
        if (! $this->post?->exists) {
            return null;
        }

        $teamId = $this->currentTeamId();

        if ($teamId === null) {
            return null;
        }

        $organizationModel = BlogConfigResolver::publicOrganizationModel();
        $pageModel = BlogConfigResolver::publicOrganizationPageModel();

        if ($organizationModel === null || $pageModel === null) {
            return null;
        }

        $organizationQuery = $organizationModel::query();
        $activeScope = (string) config('blog.public_organization.organization_active_scope', '');

        if ($activeScope !== '' && method_exists($organizationModel, 'scope'.Str::studly($activeScope))) {
            $organizationQuery->{$activeScope}();
        }

        $organizationSlug = $organizationQuery
            ->where((string) config('blog.public_organization.organization_team_column', 'team_id'), $teamId)
            ->value((string) config('blog.public_organization.organization_slug_column', 'slug'));

        if (! $organizationSlug) {
            return null;
        }

        $postsPageSlug = $pageModel::query()
            ->where((string) config('corexis.tenancy.id_column', 'team_id'), $teamId)
            ->where('is_published', true)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function ($query): void {
                $pageKey = (string) config('blog.public_organization.page_key', 'posts');
                $slugCandidates = (array) config('blog.public_organization.post_page_slugs', ['posts', 'objave']);

                $query->where('page_key', $pageKey)
                    ->orWhereIn('slug', $slugCandidates);
            })
            ->value('slug');

        if (! $postsPageSlug) {
            return null;
        }

        $routeName = (string) config('blog.public_organization.content_route_name', 'public.organization.content');

        if (! Route::has($routeName)) {
            return null;
        }

        return route($routeName, [
            'organizationSlug' => (string) $organizationSlug,
            'pageSlug' => (string) $postsPageSlug,
            'contentSlug' => (string) ($this->post->slug ?: $this->post->uuid),
        ]);
    }

    public function publicPostCanBeViewed(): bool
    {
        return (bool) $this->post?->isPublished();
    }

    /** @return array{heading: string, text: string, icon: string, color: string}|null */
    public function publicationVisibilityNotice(): ?array
    {
        if (! $this->post?->exists) {
            return null;
        }

        if ($this->post->status === 'draft') {
            return [
                'heading' => __('Objava je skica'),
                'text' => __('Objava je spremljena kao radna verzija i nije vidljiva posjetiteljima dok je ne označite kao objavljenu i spremite promjene.'),
                'icon' => 'pencil-square',
                'color' => 'amber',
            ];
        }

        if ($this->post->status === 'archived') {
            return [
                'heading' => __('Arhivirana objava je zaključana'),
                'text' => __('Ne prikazuje se javno i ne može se uređivati ni isticati. Vratite je u skicu samo ako ponovno želite raditi na sadržaju.'),
                'icon' => 'lock-closed',
                'color' => 'zinc',
            ];
        }

        if ($this->post->status !== 'published') {
            return null;
        }

        $publishedAt = $this->post->published_at;

        if (! $publishedAt) {
            return [
                'heading' => __('Objava nema datum objave'),
                'text' => __('Objava je označena kao objavljena, ali bez datuma objave nije javno vidljiva. Spremite promjene kako bi dobila sadašnji datum objave.'),
                'icon' => 'calendar-days',
                'color' => 'red',
            ];
        }

        if ($publishedAt->isFuture()) {
            return [
                'heading' => __('Objava je zakazana'),
                'text' => __('Objava nije još javno vidljiva. Prikazat će se posjetiteljima :date.', ['date' => $publishedAt->format('d.m.Y. H:i')]),
                'icon' => 'clock',
                'color' => 'blue',
            ];
        }

        return null;
    }

    private function createTaxonomyItem(string $type, string $name, string $taxonomyName, bool $multiple): TaxonomyItem
    {
        $name = trim($name);

        if ($name === '') {
            $this->addError($type === 'category' ? 'categorySearch' : 'tagSearch', __('Naziv je obavezan.'));

            $taxonomyItemModel = TaxonomyModels::taxonomyItem();

            return new $taxonomyItemModel;
        }

        $teamId = $this->currentTeamId();

        $taxonomyModel = TaxonomyModels::taxonomy();
        $taxonomyItemModel = TaxonomyModels::taxonomyItem();
        $taxonomy = $taxonomyModel::query()
            ->where('type', $type)
            ->where('slug', Str::slug($taxonomyName))
            ->when(config('corexis.tenancy.enabled', false), fn ($query) => $query->where((string) config('corexis.tenancy.id_column', 'team_id'), $teamId))
            ->first();

        if (! $taxonomy) {
            $taxonomy = new $taxonomyModel([
                'type' => $type,
                'slug' => Str::slug($taxonomyName),
                'name' => $taxonomyName,
                'is_filterable' => true,
                'is_multiple' => $multiple,
            ]);
            if (config('corexis.tenancy.enabled', false)) {
                $taxonomy->setAttribute((string) config('corexis.tenancy.id_column', 'team_id'), $teamId);
            }

            $taxonomy->save();
        }

        $item = $taxonomyItemModel::query()
            ->where('taxonomy_id', $taxonomy->id)
            ->where('slug', Str::slug($name))
            ->when(config('corexis.tenancy.enabled', false), fn ($query) => $query->where((string) config('corexis.tenancy.id_column', 'team_id'), $teamId))
            ->first();

        if (! $item) {
            $item = new $taxonomyItemModel([
                'taxonomy_id' => $taxonomy->id,
                'slug' => Str::slug($name),
                'name' => $name,
            ]);
            if (config('corexis.tenancy.enabled', false)) {
                $item->setAttribute((string) config('corexis.tenancy.id_column', 'team_id'), $teamId);
            }

            $item->save();
        }

        return $item;
    }

    /**
     * @param  array<int, string>  $selectedUuids
     */
    private function taxonomyItems(string $type, string $search, array $selectedUuids = []): Collection
    {
        $search = trim($search);

        $taxonomyItemModel = TaxonomyModels::taxonomyItem();
        $results = $taxonomyItemModel::query()
            ->forType($type)
            ->when(config('corexis.tenancy.enabled', false), fn ($query) => $query->where((string) config('corexis.tenancy.id_column', 'team_id'), $this->currentTeamId()))
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

        return $taxonomyItemModel::query()
            ->whereIn('uuid', $missingSelectedUuids)
            ->when(config('corexis.tenancy.enabled', false), fn ($query) => $query->where((string) config('corexis.tenancy.id_column', 'team_id'), $this->currentTeamId()))
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

    private function captureSavedStateSnapshot(): void
    {
        $this->savedStateSnapshot = $this->currentStateSnapshot();
        $this->lastSavedAt = $this->post?->exists
            ? Carbon::parse($this->post->updated_at ?? now())->timezone(config('app.timezone'))->format('d.m.Y. H:i')
            : null;
        $this->lastSavedBy = $this->post?->exists
            ? (string) (data_get($this->post, 'updatedBy.name') ?: data_get($this->post, 'author.name') ?: '')
            : null;
    }

    private function currentStateSnapshot(): array
    {
        return $this->normalizeState([
            'title' => $this->form->title,
            'content' => $this->form->content,
            'status' => $this->form->status,
            'categoryUuids' => $this->form->categoryUuids,
            'tagUuids' => $this->form->tagUuids,
            'featuredImageUpload' => $this->form->featuredImageUpload instanceof TemporaryUploadedFile,
            'removeFeaturedImage' => $this->form->removeFeaturedImage,
            'published_at' => $this->form->published_at,
            'is_featured' => $this->form->is_featured,
        ]);
    }

    private function normalizeState(array $state): array
    {
        foreach ($state as $key => $value) {
            if (is_array($value)) {
                $value = array_map(
                    fn (mixed $item): mixed => is_array($item) ? $this->normalizeState($item) : $this->normalizeScalarState($item),
                    $value,
                );

                in_array($key, ['categoryUuids', 'tagUuids'], true)
                    ? sort($value)
                    : ksort($value);

                $state[$key] = $value;

                continue;
            }

            $state[$key] = $this->normalizeScalarState($value);
        }

        ksort($state);

        return $state;
    }

    private function normalizeScalarState(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value) || $value === null) {
            return $value;
        }

        return trim((string) $value);
    }

    private function postForCurrentTeam(string $uuid): Post
    {
        $model = BlogModels::post();

        return $model::query()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function currentTeamId(): ?int
    {
        $tenantId = corexis_tenant_id();

        return is_numeric($tenantId) ? (int) $tenantId : null;
    }

    private function currentLocaleCode(): string
    {
        return corexis_locale_code() ?: config('app.locale', 'en');
    }

    private function toastFromResult(ActionResult $result): void
    {
        Flux::toast(variant: $result->success ? 'success' : 'danger', text: $result->message);
    }
}
