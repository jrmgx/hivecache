export interface IriMatcher {
  /**
   * Pattern to match against the IRI path
   * Can be a regex pattern or a simple string pattern
   */
  pattern: RegExp | string;

  /**
   * Function that converts the matched IRI to a client route
   * @param _iri - The full IRI URL (may be used for context in future)
   * @param matches - Regex match groups if pattern is a RegExp
   * @returns Client route path (e.g., "/bookmarks/123" or "/tags?tag=example")
   */
  toRoute: (_iri: string, matches: RegExpMatchArray | null) => string | null;
}

/**
 * Registered IRI matchers
 * Add new matchers here to extend functionality
 */
const iriMatchers: IriMatcher[] = [
  // Match: /users/me/bookmarks/{uuid}
  {
    pattern: /\/users\/me\/bookmarks\/([^/]+)$/,
    toRoute: (_iri, matches) => {
      if (matches && matches[1]) {
        return `/bookmarks/${matches[1]}`;
      }
      return null;
    },
  },

  // Match: /users/me/tags/{tagname}
  {
    pattern: /\/users\/me\/tags\/([^/]+)$/,
    toRoute: (_iri, matches) => {
      if (matches && matches[1]) {
        const tagSlug = decodeURIComponent(matches[1]);
        // Navigate to home page with tag filter applied
        return `/?tags=${encodeURIComponent(tagSlug)}`;
      }
      return null;
    },
  },

  // Match: /users/me
  {
    pattern: /\/users\/me\/?$/,
    toRoute: () => '/',
  },
];

/**
 * Parse an IRI and convert it to a client route
 *
 * @param iri - The IRI to parse (can be full URL or path)
 * @returns The client route path, or null if no match found
 */
export const iriToRoute = (iri: string): string | null => {
  if (!iri) {
    return null;
  }

  // Extract path from full URL if needed
  let path: string;
  try {
    const url = new URL(iri);
    path = url.pathname;
  } catch {
    // If it's not a valid URL, treat it as a path
    path = iri.startsWith('/') ? iri : `/${iri}`;
  }

  // Try each matcher in order
  for (const matcher of iriMatchers) {
    let matches: RegExpMatchArray | null = null;

    if (matcher.pattern instanceof RegExp) {
      matches = path.match(matcher.pattern);
      if (matches) {
        const route = matcher.toRoute(iri, matches);
        if (route) {
          return route;
        }
      }
    } else {
      // String pattern matching (simple equality check)
      if (path === matcher.pattern || path.startsWith(matcher.pattern + '/')) {
        const route = matcher.toRoute(iri, null);
        if (route) {
          return route;
        }
      }
    }
  }

  return null;
};

/**
 * Register a new IRI matcher
 * Useful for extending functionality without modifying core matchers
 *
 * @param matcher - The IRI matcher to register
 */
export const registerIriMatcher = (matcher: IriMatcher): void => {
  iriMatchers.push(matcher);
};

