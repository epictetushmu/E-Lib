import MarkdownIt from 'markdown-it'
import katexPlugin from '@vscode/markdown-it-katex'
import katex from 'katex'
import hljs from 'highlight.js/lib/common'
import 'katex/dist/katex.min.css'
import '@/css/diagram-shapes.css'
import { parseDiagram, renderDiagramSvg } from '@/utils/diagram'

/**
 * Renders a reader's note: Markdown, $inline$ / $$block$$ maths (KaTeX), highlighted ```code```,
 * and ```diagram blocks drawn as SVG. Raw HTML in notes is escaped, never rendered.
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
  // Drawn in the visual editor.
  if (token.info.trim() === 'diagram') {
    try {
      const svg = renderDiagramSvg(parseDiagram(token.content))
      return `<div class="note-diagram note-drawn" role="button" tabindex="0" aria-label="Open diagram full size" title="Open full size">${svg}</div>`
    } catch {
      return '<div class="note-diagram is-invalid">This diagram couldn\'t be read. Open it in the editor and insert it again.</div>'
    }
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
