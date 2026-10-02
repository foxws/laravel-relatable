---
title: Introduction
metadata:
  role: Relations
  group: search
  eyebrow: "Eloquent · Relations · Scoring"
  desc: "Relate Eloquent models to other models, with a base score and boost to control their priority."
  lead: "Weighted, one-way relations between any Eloquent models, for ranked lists like related videos or tag suggestions."
  requires: "PHP ^8.3"
  laravel: "12.x / 13.x"
  licence: MIT
  used_by:
    - name: Stry
      desc: "A self-hosted video streaming app."
      href: "https://github.com/francoism90/stry"
---

# Introduction

Laravel Relatable lets you relate any Eloquent model to any other, and say how strongly they're related. A tag can relate to other tags, a video to tags, and a post to videos. You then get the related models back, strongest first. That's what you need for lists like "related videos" or "suggested tags".

```php
use Foxws\Relatable\Concerns\InteractsWithRelated;

class Tag extends Model
{
    use InteractsWithRelated;
}

$action->attachRelated($chase, score: 0.8);
$action->attachRelated($comedy, score: 0.3);
```

## How it works

Every relation has a **score** and a **boost**. Multiplied together, they give the relation's **weight**, and related models are ordered by weight. See [Scoring](scoring.md).

Relations go one way. If *Action* relates to *Comedy*, *Comedy* doesn't relate back to *Action* unless you add that too, and it can have a different score. A genre may matter a lot to a film, while the film is just one of many in that genre.

## Installation

```bash
composer require foxws/laravel-relatable
```

The migration for the `relatables` table runs automatically.

## Learn more

- [Installation](installation.md)
- [Usage](usage.md): attaching, detaching, syncing and retrieving related models.
- [Scoring](scoring.md)
- [Configuration](configuration.md)
