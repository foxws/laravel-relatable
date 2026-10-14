---
title: Introduction
metadata:
  role: Relations
  group: search
  eyebrow: "Eloquent · Relations · Scoring"
  desc: "Relate Eloquent models to other models, with a base score and boost to control their priority."
  requires: "PHP ^8.3"
  laravel: "12.x / 13.x"
  licence: MIT
---

# Laravel Relatable

Relate Eloquent models to other models, of any type, and control how strongly
they relate. A tag can relate to other tags, a video to tags, a post to
videos — each relation is a row in the `relatables` table.

> [!TIP]
> Let your AI agent do the hard parts. This package ships a [Laravel Boost](https://laravel.com/docs/boost) skill, `relatable-development`, that teaches your agent how to install, configure and use Laravel Relatable. Install Boost and select the skill when prompted:
>
> ```bash
> composer require laravel/boost --dev
> php artisan boost:install
> ```
>
> Already using Boost? Run `php artisan boost:update --discover` to pick up the skill. Then just ask, for example: *"Relate my Video model to tags and order them by weight."*

## Directed relations

Relations are directed. When *Action* relates to *Genre*, *Genre* doesn't relate to
*Action* unless you say so — and when it does, it gets a score of its own. Two
models can relate to each other, yet still differ in how much they do.

## Scoring

Each relation has a base `score` and a `boost`. Their product is the relation's
**weight**, which orders related models (highest first). See
[scoring.md](scoring.md).

- [Installation](installation.md)
- [Usage](usage.md)
- [Scoring](scoring.md)
- [Configuration](configuration.md)
