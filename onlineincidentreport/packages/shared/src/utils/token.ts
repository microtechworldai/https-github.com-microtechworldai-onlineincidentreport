/**
 * Safely extracts the canonical site token from various input formats:
 * - Direct token: "marina7a9b2c3d4e5f60718293a4b5c6d7"
 * - Full URL: "https://incidents.uss-security.ae/g/marina7a9b2c3d4e5f60718293a4b5c6d7"
 * - Local URL: "http://localhost:5173/g/marina7a9b2c3d4e5f60718293a4b5c6d7"
 * - Route path: "/g/marina7a9b2c3d4e5f60718293a4b5c6d7"
 * - URL with queries/hash: ".../g/marina7a9b2c3d4e5f60718293a4b5c6d7?ref=poster#start"
 */
export function extractSiteToken(input: string | null | undefined): string {
  if (!input) return '';
  let token = String(input).trim();
  
  // Strip surrounding quotes
  token = token.replace(/^["']|["']$/g, '').trim();

  // 1. Check for ?token= or &token= in query parameters
  const qMatch = token.match(/[?&]token=([a-zA-Z0-9_-]+)/i);
  if (qMatch && qMatch[1]) {
    return qMatch[1].trim();
  }

  // 2. Look for /g/<token> pattern (ignoring index.php / index)
  const gMatch = token.match(/\/g\/([a-zA-Z0-9_-]+)/i);
  if (gMatch && gMatch[1] && !gMatch[1].toLowerCase().startsWith('index')) {
    return gMatch[1].trim();
  }

  // 3. Handle URL format
  try {
    if (token.startsWith('http://') || token.startsWith('https://')) {
      const url = new URL(token);
      const urlTokenParam = url.searchParams.get('token');
      if (urlTokenParam) {
        return urlTokenParam.trim();
      }
      const segments = url.pathname.split('/').filter(Boolean);
      if (segments.length > 0 && !segments[segments.length - 1].toLowerCase().startsWith('index')) {
        return segments[segments.length - 1].trim();
      }
    }
  } catch {
    // If URL parsing fails, continue with string extraction
  }

  // Strip query strings and hash fragments
  token = token.split('?')[0].split('#')[0].trim();

  // If path segments remain
  const parts = token.split('/').filter(Boolean);
  if (parts.length > 0) {
    const last = parts[parts.length - 1].trim();
    if (!last.toLowerCase().startsWith('index')) {
      token = last;
    }
  }

  return token;
}
