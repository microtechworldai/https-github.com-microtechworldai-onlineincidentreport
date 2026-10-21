/**
 * Rich-Text Sanitization and Utility Functions for USS Incident Reporting
 * Allows safe formatting: font size, bold, italic, underline, color, line-spacing, lists.
 * Strictly strips dangerous scripts, iframes, objects, external resources and event handlers.
 */

const ALLOWED_TAGS = new Set([
  'p', 'span', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'br', 'div'
]);

const ALLOWED_STYLES = new Set([
  'font-size', 'color', 'line-height', 'font-weight', 'font-style', 'text-decoration'
]);

/**
 * Filter inline style string to strictly allowed safe properties
 */
export function sanitizeInlineStyle(styleStr: string): string {
  if (!styleStr || typeof styleStr !== 'string') return '';
  const declarations = styleStr.split(';');
  const safeDeclarations: string[] = [];

  for (const decl of declarations) {
    const [rawProp, rawVal] = decl.split(':');
    if (!rawProp || !rawVal) continue;
    const prop = rawProp.trim().toLowerCase();
    const val = rawVal.trim();

    if (!ALLOWED_STYLES.has(prop)) continue;

    // Block expression, url, javascript in values
    if (/javascript|expression|url|<|>/i.test(val)) continue;

    // Validate color
    if (prop === 'color') {
      if (/^#[0-9a-f]{3,8}$/i.test(val) || /^rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)$/i.test(val) || /^[a-z]+$/i.test(val)) {
        safeDeclarations.push(`${prop}: ${val}`);
      }
    }
    // Validate font-size (10pt to 28pt or 12px to 36px)
    else if (prop === 'font-size') {
      const match = val.match(/^(\d+(?:\.\d+)?)(pt|px|em|rem)?$/i);
      if (match) {
        const num = parseFloat(match[1]);
        const unit = (match[2] || 'pt').toLowerCase();
        if (unit === 'pt' && num >= 8 && num <= 32) {
          safeDeclarations.push(`${prop}: ${num}pt`);
        } else if (unit === 'px' && num >= 10 && num <= 42) {
          safeDeclarations.push(`${prop}: ${num}px`);
        }
      }
    }
    // Validate line-height (1.0 to 2.5)
    else if (prop === 'line-height') {
      const num = parseFloat(val);
      if (!isNaN(num) && num >= 0.8 && num <= 3.0) {
        safeDeclarations.push(`${prop}: ${num}`);
      }
    }
    // Validate font-weight
    else if (prop === 'font-weight') {
      if (/^(bold|normal|[1-9]00)$/i.test(val)) {
        safeDeclarations.push(`${prop}: ${val}`);
      }
    }
    // Validate font-style
    else if (prop === 'font-style') {
      if (/^(italic|normal|oblique)$/i.test(val)) {
        safeDeclarations.push(`${prop}: ${val}`);
      }
    }
    // Validate text-decoration
    else if (prop === 'text-decoration') {
      if (/^(underline|none)$/i.test(val)) {
        safeDeclarations.push(`${prop}: ${val}`);
      }
    }
  }

  return safeDeclarations.join('; ');
}

/**
 * Convert formatted HTML or plain text into clean plain text for search and Excel
 */
export function htmlToPlainText(html: string): string {
  if (!html) return '';
  let text = String(html);
  
  // Replace <br>, </p>, </li>, </div> with newlines
  text = text.replace(/<br\s*[\/]?>/gi, '\n');
  text = text.replace(/<\/p>/gi, '\n');
  text = text.replace(/<\/div>/gi, '\n');
  text = text.replace(/<\/li>/gi, '\n');
  text = text.replace(/<li>/gi, '• ');

  // Strip all other HTML tags
  text = text.replace(/<[^>]+>/g, '');

  // Decode common HTML entities
  text = text
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'");

  // Collapse excessive empty newlines (more than 2 to 2)
  text = text.replace(/\n{3,}/g, '\n\n').trim();
  return text;
}

/**
 * Sanitize rich text input, returning safe HTML, plain text, and line count
 */
export function sanitizeRichText(input: string): {
  html: string;
  text: string;
  lineCount: number;
} {
  if (!input || typeof input !== 'string') {
    return { html: '', text: '', lineCount: 0 };
  }

  let raw = input.trim();

  // If input is plain text (no HTML tags), wrap paragraphs
  if (!/<[a-z][\s\S]*>/i.test(raw)) {
    const escaped = raw
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    const paragraphs = escaped.split(/\r?\n/).map((line) => line ? `<p>${line}</p>` : '<br/>');
    const html = paragraphs.join('');
    const text = raw;
    const lines = text.split(/\r?\n/).length;
    return { html, text, lineCount: lines };
  }

  // Remove dangerous script/style/iframe tags and their contents completely
  raw = raw.replace(/<(script|style|iframe|object|embed|applet)[\s\S]*?<\/\1>/gi, '');

  // Strip attributes other than style from allowed tags
  // Parse tags with regex
  const sanitizedHtml = raw.replace(/<\/?([a-zA-Z0-9]+)([^>]*)>/g, (match, tag, attrString) => {
    const lowerTag = tag.toLowerCase();
    const isClosing = match.startsWith('</');

    if (!ALLOWED_TAGS.has(lowerTag)) {
      return ''; // Strip tag completely
    }

    if (isClosing) {
      return `</${lowerTag}>`;
    }

    // Extract style attribute if present
    let safeStyle = '';
    const styleMatch = attrString.match(/style=(["'])(.*?)\1/i);
    if (styleMatch) {
      safeStyle = sanitizeInlineStyle(styleMatch[2]);
    }

    if (safeStyle) {
      return `<${lowerTag} style="${safeStyle}">`;
    }
    return `<${lowerTag}>`;
  });

  const text = htmlToPlainText(sanitizedHtml);
  const lineCount = text ? text.split(/\r?\n/).length : 0;

  return {
    html: sanitizedHtml,
    text,
    lineCount,
  };
}
