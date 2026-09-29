<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Page extends Model implements HasMedia
{
    use HasSlug;
    use InteractsWithMedia;

    protected $fillable = [
        'title',
        'slug',
        'is_homepage',
        'excerpt',
        'blocks',
        'is_published',
        'published_at',
        'in_hub',
        'meta_title',
        'meta_description',
        'canonical_url',
        'og_title',
        'og_description',
        'robots_index',
        'robots_follow',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_homepage' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'in_hub' => 'boolean',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('gallery');
        $this->addMediaCollection('og_image')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(300)
            ->nonQueued();

        $this->addMediaConversion('large')
            ->width(1600)
            ->nonQueued();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeInHub(Builder $query): Builder
    {
        return $query->where('in_hub', true);
    }

    public function getMetaTitleForSeo(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function getMetaDescriptionForSeo(): ?string
    {
        return $this->meta_description ?: $this->excerpt;
    }
}
