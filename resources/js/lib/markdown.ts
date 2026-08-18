import DOMPurify from 'dompurify';
import { Marked } from 'marked';

const marked = new Marked({
    async: false,
    gfm: true,
    breaks: true,
});

const ALLOWED_TAGS = [
    'p',
    'br',
    'strong',
    'em',
    'del',
    's',
    'ul',
    'ol',
    'li',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'blockquote',
    'code',
    'pre',
    'a',
    'hr',
    'table',
    'thead',
    'tbody',
    'tr',
    'th',
    'td',
];

const ALLOWED_ATTR = ['href', 'target', 'rel'];

export function renderMarkdown(content: string): string {
    if (!content.trim()) {
        return '';
    }

    const html = marked.parse(content) as string;

    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS,
        ALLOWED_ATTR,
    }).replaceAll(
        '<a ',
        '<a target="_blank" rel="noopener noreferrer" ',
    );
}
