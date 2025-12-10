import fs from 'node:fs'
import path from 'node:path'
import { defineConfig } from 'vitepress'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

function getPackagesSidebar() {
  const packagesDir = path.resolve(__dirname, '../packages')
  const items = []

  if (fs.existsSync(packagesDir)) {
    const packages = fs.readdirSync(packagesDir)
      .filter(pkg => fs.statSync(path.join(packagesDir, pkg)).isDirectory())
      .sort()

    packages.forEach(pkg => {
      const pkgDir = path.join(packagesDir, pkg)
      const files = fs.readdirSync(pkgDir)
        .filter(file => file.endsWith('.md'))

      if (files.length === 0) return

      const pkgItems = files.map(file => {
        const name = path.basename(file, '.md')
        let text = name
          .split('-')
          .map(word => word.charAt(0).toUpperCase() + word.slice(1))
          .join(' ')

        if (name === 'index') text = 'Overview'

        return {
          text,
          link: `/packages/${pkg}/${name === 'index' ? '' : name}`
        }
      }).sort((a, b) => {
        // Overview first
        if (a.text === 'Overview') return -1
        if (b.text === 'Overview') return 1
        return a.text.localeCompare(b.text)
      })

      items.push({
        text: pkg.charAt(0).toUpperCase() + pkg.slice(1),
        collapsed: true,
        items: pkgItems
      })
    })
  }

  return items
}

export default defineConfig({
  title: 'Lalaz',
  description: 'A modern PHP framework for building web applications',

  lang: 'en-US',

  head: [
    ['meta', { name: 'theme-color', content: '#3eaf7c' }],
    ['meta', { name: 'og:type', content: 'website' }],
    ['meta', { name: 'og:site_name', content: 'Lalaz' }],
  ],

  themeConfig: {
    logo: '/lalaz-logo.svg',
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Start Here', link: '/start-here/introduction' },
      { text: 'Packages', link: '/packages/auth/' },
      {
        text: 'Ecosystem',
        items: [
          { text: 'GitHub', link: 'https://github.com/lalaz-foundation/lalaz' },
          { text: 'Releases', link: 'https://github.com/lalaz-foundation/lalaz/releases' },
        ]
      }
    ],

    sidebar: [
      {
        text: 'Start Here',
        items: [
          { text: 'Introduction', link: '/start-here/introduction' },
          { text: 'What is Lalaz?', link: '/start-here/what-is-lalaz' },
          { text: 'API Quickstart', link: '/start-here/api-quickstart' },
          { text: 'Web Quickstart', link: '/start-here/web-quickstart' },
        ]
      },
      {
        text: 'Essentials',
        items: [
          { text: 'Configuration', link: '/essentials/configuration' },
          { text: 'Routing', link: '/essentials/routing' },
          { text: 'Controllers', link: '/essentials/controllers' },
          { text: 'Middleware', link: '/essentials/middleware' },
          { text: 'Requests', link: '/essentials/requests' },
          { text: 'Responses', link: '/essentials/responses' },
          { text: 'Validation', link: '/essentials/validation' },
          { text: 'Database', link: '/essentials/database' },
          { text: 'CLI Commands', link: '/essentials/cli' },
        ]
      },
      // {
      //   text: 'Packages',
      //   items: getPackagesSidebar()
      // }
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/lalaz-foundation/lalaz' }
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: `Copyright © ${new Date().getFullYear()} Lalaz Foundation`
    },

    search: {
      provider: 'local'
    },

    editLink: {
      pattern: 'https://github.com/lalaz-foundation/lalaz/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    }
  },

  markdown: {
    lineNumbers: true,
    theme: {
      light: 'github-light',
      dark: 'github-dark'
    }
  },

  ignoreDeadLinks: [
    /localhost/,
  ],

  sitemap: {
    hostname: 'https://docs.lalaz.dev'
  }
})
