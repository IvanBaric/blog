<?php

use Illuminate\Support\Facades\Route;
use IvanBaric\Blog\Livewire\Admin\PostForm;
use IvanBaric\Blog\Livewire\Admin\PostIndex;

Route::get('/', PostIndex::class)->name('index');
Route::get('/create', PostForm::class)->name('create');
Route::get('/{post:uuid}/edit', PostForm::class)->name('edit');
