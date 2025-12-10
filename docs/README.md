# Documentation Configuration

This directory contains the Lalaz Framework documentation used to build the docs site (docs.lalaz.dev).

## Directory structure

```
docs/
├── index.md                    # Home page
├── .vitepress/                 # VitePress configuration (optional)
│   └── config.ts
├── getting-started/            # Getting started guide
│   ├── installation.md
│   ├── quick-start.md
│   └── configuration.md
└── packages/                   # Package documentation
    ├── auth/
    │   ├── index.md            # Overview
    │   ├── configuration.md    # Configuration
    │   ├── guards.md           # Authentication guards
    │   ├── jwt.md              # JWT tokens
    │   ├── middleware.md       # Middleware
    │   ├── user-models.md      # User models
    │   └── api-reference.md    # API reference
    ├── cache/
    ├── database/
    ├── events/
    ├── orm/
    ├── queue/
    ├── reactive/
    ├── scheduler/
    ├── storage/
    ├── validator/
    ├── waf/
    ├── web/
    └── wire/
```

## Site build

### VitePress (recommended)

```bash
# Install dependencies
npm install -D vitepress

# Development
npm run docs:dev

# Build
npm run docs:build

# Preview
npm run docs:preview
```

### Docusaurus

```bash
# Create a Docusaurus project
npx create-docusaurus@latest docs-site classic

# Copy markdown content
cp -r docs/* docs-site/docs/

# Development
cd docs-site && npm start
```


## File format

All documentation pages use YAML frontmatter:

```markdown
---
title: Page Title
description: SEO-friendly description
---

# Content here
```

## Conventions

1. **Links**: Use relative paths (`/packages/auth/guards`)
2. **Code**: Use fenced code blocks with explicit language
3. **Tables**: Use standard Markdown table syntax
4. **Images**: Place images under `docs/public/images/`

## Automated build

Configure GitHub Actions for automatic deployment:

```yaml
# .github/workflows/docs.yml
name: Deploy Docs

on:
  push:
    branches: [main]
    paths:
      - 'docs/**'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 20
      - run: npm ci
      - run: npm run docs:build
      - uses: peaceiris/actions-gh-pages@v3
        with:
          github_token: ${{ secrets.GITHUB_TOKEN }}
          publish_dir: docs/.vitepress/dist
```
