<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'blog.admin.posts.index')->name('index');
Route::livewire('/categories', 'blog.admin.post-taxonomies')->defaults('type', 'category')->name('categories');
Route::livewire('/tags', 'blog.admin.post-taxonomies')->defaults('type', 'tags')->name('tags');
Route::livewire('/{post:uuid}/edit', 'blog.admin.posts.form')->name('edit');
