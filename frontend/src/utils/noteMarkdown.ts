import MarkdownIt from 'markdown-it'
import katexPlugin from '@vscode/markdown-it-katex'
import katex from 'katex'
import hljs from 'highlight.js/lib/common'
import 'katex/dist/katex.min.css'

/**
 * Renders a reader's note: Markdown, $inline$ / $$block$$ maths (KaTeX), highlighted ```code```,
 * and ```mermaid blocks left as placeholders for renderDiagrams(). Raw HTML in notes is escaped, never rendered.
 */
const md = new MarkdownIt({
  html: false,
  linkify: true,
  breaks: true,
  highlight(code, lang) {
    if (lang && hljs.getLanguage(lang)) {
      return hljs.highlight(code, { language: lang, ignoreIllegals: true }).value
    }
    return ''
  },
})

// The package is CommonJS (`exports.default`); depending on interop the function is the import or its .default.
const plugin = (katexPlugin as unknown as { default?: typeof katexPlugin }).default ?? katexPlugin
md.use(plugin, { throwOnError: false, katex })

const defaultFence = md.renderer.rules.fence!
md.renderer.rules.fence = (tokens, idx, options, env, self) => {
  const token = tokens[idx]
  if (token.info.trim() === 'mermaid') {
    return `<div class="note-diagram" data-mermaid>${md.utils.escapeHtml(token.content)}</div>`
  }
  return defaultFence(tokens, idx, options, env, self)
}

// Links in notes leave the reader in a new tab.
const defaultLinkOpen = md.renderer.rules.link_open ?? ((tokens, idx, options, _env, self) => self.renderToken(tokens, idx, options))
md.renderer.rules.link_open = (tokens, idx, options, env, self) => {
  tokens[idx].attrSet('target', '_blank')
  tokens[idx].attrSet('rel', 'noopener noreferrer')
  return defaultLinkOpen(tokens, idx, options, env, self)
}

export function renderNote(text: string): string {
  return md.render(text)
}

let diagramCount = 0

/**
 * Draws every ```mermaid placeholder inside `root`. Mermaid is ~1 MB, so it's only fetched
 * once a note actually contains a diagram. Colours come from the reader's current theme tokens.
 */
export async function renderDiagrams(root: HTMLElement): Promise<void> {
  const blocks = [...root.querySelectorAll<HTMLElement>('[data-mermaid]')]
  if (!blocks.length) return

  const { default: mermaid } = await import('mermaid')
  const css = getComputedStyle(root)
  const token = (name: string, fallback: string) => css.getPropertyValue(name).trim() || fallback
  mermaid.initialize({
    startOnLoad: false,
    securityLevel: 'strict',
    theme: 'base',
    fontFamily: token('--font-sans', 'sans-serif'),
    themeVariables: {
      fontSize: '13px',
      background: token('--color-surface', '#fbf8f1'),
      primaryColor: token('--color-bg', '#ece3d2'),
      primaryTextColor: token('--color-text', '#211c30'),
      primaryBorderColor: token('--color-text-muted', '#5a5340'),
      lineColor: token('--color-text-muted', '#5a5340'),
      secondaryColor: token('--color-surface', '#fbf8f1'),
      tertiaryColor: token('--color-surface', '#fbf8f1'),
    },
  })

  for (const block of blocks) {
    const source = block.textContent ?? ''
    try {
      const { svg } = await mermaid.render(`note-diagram-${++diagramCount}`, source)
      block.innerHTML = svg
      block.removeAttribute('data-mermaid')
      // Fitted to the panel as a preview; NoteContent opens it full size on click or Enter.
      block.tabIndex = 0
      block.setAttribute('role', 'button')
      block.setAttribute('aria-label', 'Open diagram full size')
      block.title = 'Open full size'
    } catch (err) {
      block.classList.add('is-invalid')
      block.textContent = `This diagram couldn't be drawn: ${err instanceof Error ? err.message.split('\n')[0] : 'check the syntax'}\n\n${source}`
    }
  }
}
