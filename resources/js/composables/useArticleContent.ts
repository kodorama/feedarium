import { ref } from 'vue';

/**
 * Shared article content processing for reader modals.
 *
 * rawMode is module-level so it persists across article navigation.
 * It is initialised from localStorage so the user's default preference
 * survives page refreshes. The in-modal toggle overrides it for the
 * current session without writing back to localStorage.
 */
const RAW_MODE_KEY = 'feedarium.raw_mode_default';

const rawMode = ref<boolean>(typeof window !== 'undefined' ? localStorage.getItem(RAW_MODE_KEY) === 'true' : false);

function isImageUrl(url: string): boolean {
    return /\.(jpe?g|png|gif|webp|svg|avif|bmp)(\?.*)?$/i.test(url);
}

/**
 * Auto-link bare URLs in text nodes that are not already inside an anchor,
 * script, style, code, or pre element.
 */
function autolinkTextNodes(doc: Document): void {
    const urlRegex = /https?:\/\/[^\s<>"]+[^\s<>".,!?;:)]/g;

    const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT);
    const textNodes: Text[] = [];
    let node: Text | null;

    while ((node = walker.nextNode() as Text | null) !== null) {
        const parent = node.parentElement;
        if (parent?.closest('a, script, style, code, pre')) continue;
        if (urlRegex.test(node.textContent ?? '')) {
            textNodes.push(node);
        }
        urlRegex.lastIndex = 0;
    }

    for (const textNode of textNodes) {
        const text = textNode.textContent ?? '';
        urlRegex.lastIndex = 0;

        const fragment = doc.createDocumentFragment();
        let lastIndex = 0;
        let match: RegExpExecArray | null;

        while ((match = urlRegex.exec(text)) !== null) {
            if (match.index > lastIndex) {
                fragment.appendChild(doc.createTextNode(text.slice(lastIndex, match.index)));
            }

            const a = doc.createElement('a');
            a.setAttribute('href', match[0]);
            a.setAttribute('target', '_blank');
            a.setAttribute('rel', 'noopener noreferrer');
            a.textContent = match[0];
            fragment.appendChild(a);

            lastIndex = match.index + match[0].length;
        }

        if (lastIndex < text.length) {
            fragment.appendChild(doc.createTextNode(text.slice(lastIndex)));
        }

        textNode.parentNode?.replaceChild(fragment, textNode);
    }
}

/**
 * Process article HTML:
 *  - Auto-link bare URLs in text
 *  - Add target="_blank" + rel to all anchors
 *  - Inject <img> inside anchors that point to image URLs
 */
export function processArticleHtml(html: string | null): string {
    if (!html) return '';

    const doc = new DOMParser().parseFromString(html, 'text/html');

    autolinkTextNodes(doc);

    doc.querySelectorAll('a').forEach((anchor) => {
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener noreferrer');

        const href = anchor.getAttribute('href') ?? '';
        if (href && isImageUrl(href) && !anchor.querySelector('img')) {
            const img = doc.createElement('img');
            img.setAttribute('src', href);
            img.setAttribute('alt', anchor.textContent?.trim() ?? '');
            img.setAttribute('loading', 'lazy');
            img.setAttribute('referrerpolicy', 'no-referrer');
            img.style.maxWidth = '100%';
            img.style.height = 'auto';
            anchor.appendChild(img);
        }
    });

    return doc.body.innerHTML;
}

export function stripHtml(html: string | null): string {
    if (!html) return '';
    const doc = new DOMParser().parseFromString(html, 'text/html');
    return doc.body.textContent ?? '';
}

/** Block-level tags that get a trailing newline when serialized. */
const BLOCK_TAGS = new Set(['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'li', 'td', 'th', 'tr']);

/** Tags whose entire subtree is silently dropped. */
const DROP_TAGS = new Set(['video', 'audio', 'picture', 'svg', 'canvas', 'script', 'style', 'noscript']);

function isSafeUrl(url: string): boolean {
    try {
        const { protocol } = new URL(url);
        return protocol === 'http:' || protocol === 'https:';
    } catch {
        return false;
    }
}

function serializeNode(node: Node): string {
    if (node.nodeType === Node.TEXT_NODE) {
        const text = node.textContent ?? '';
        return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    if (node.nodeType !== Node.ELEMENT_NODE) return '';

    const el = node as Element;
    const tag = el.tagName.toLowerCase();

    if (DROP_TAGS.has(tag)) return '';

    // Images: render safely without any surrounding anchor (caller strips it)
    if (tag === 'img') {
        const src = el.getAttribute('src') ?? '';
        if (!src || !isSafeUrl(src)) return '';
        const esc = (s: string) => s.replace(/"/g, '&quot;');
        const alt = esc(el.getAttribute('alt') ?? '');
        const title = el.getAttribute('title') ? ` title="${esc(el.getAttribute('title') ?? '')}"` : '';
        return `<img src="${esc(src)}" alt="${alt}"${title} loading="lazy" referrerpolicy="no-referrer" style="max-width:100%;height:auto">`;
    }

    let children = '';
    for (const child of node.childNodes) {
        children += serializeNode(child);
    }

    if (tag === 'br') return '\n';

    if (tag === 'a') {
        const href = el.getAttribute('href') ?? '';
        // An anchor with no visible text is image-only (or empty): strip the anchor
        // but keep the serialized children (the <img> tags) so images remain visible.
        const hasVisibleText = (el.textContent ?? '').trim().length > 0;
        if (!hasVisibleText) {
            return children;
        }
        // Text link: keep as a clickable anchor with a validated href
        if (href && isSafeUrl(href) && children.trim()) {
            const safeHref = href.replace(/"/g, '&quot;');
            return `<a href="${safeHref}" target="_blank" rel="noopener noreferrer">${children}</a>`;
        }
        return children;
    }

    if (BLOCK_TAGS.has(tag)) {
        const inner = children.trim();
        return inner ? inner + '\n\n' : '';
    }

    return children;
}

/**
 * Converts article HTML to a simplified readable form:
 *  - strips all tags except <a> (which are kept when they contain visible text)
 *  - image-only anchors disappear because their <img> children are dropped first
 *  - safe to render with v-html (only <a> tags with validated http/https hrefs survive)
 */
export function getRawContent(html: string | null): string {
    if (!html) return '';
    const doc = new DOMParser().parseFromString(html, 'text/html');
    return serializeNode(doc.body).trim();
}

export function useArticleContent() {
    function getArticleContent(article: { full_body: string | null; description: string | null }): string {
        const content = article.full_body ?? article.description ?? '';
        return rawMode.value ? getRawContent(content) : processArticleHtml(content);
    }

    function hasContent(article: { full_body: string | null; description: string | null }): boolean {
        return !!(article.full_body || article.description);
    }

    /** Persist the default raw-mode preference to localStorage and apply it immediately. */
    function setRawModeDefault(value: boolean): void {
        rawMode.value = value;
        if (typeof window !== 'undefined') {
            localStorage.setItem(RAW_MODE_KEY, value ? 'true' : 'false');
        }
    }

    return { rawMode, getArticleContent, hasContent, stripHtml, processArticleHtml, setRawModeDefault };
}
