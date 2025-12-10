---
id: framework-contribute
title: "Contributing — Docs & RAG-friendly content"
short_description: "How to contribute documentation and make it RAG-ready."
tags: ["contribute","docs","rag"]
version: "1.0"
type: "meta"
---

# Contributing — Docs & RAG-friendly content

Short tips for contributors:
- Keep paragraphs short and factual.
- Add a `short_description` frontmatter with 1–2 sentences for each page.
- Add a 3-line `Quick facts` summary near the top of the page for retrieval.
- Include at least one short example snippet for API pages.
- Use unique `id` frontmatter values to make each doc retrievable by RAG systems.

Linting:
- The repo provides `tools/validate-docs.php` — run it to check API coverage and docs consistency before opening PRs.
