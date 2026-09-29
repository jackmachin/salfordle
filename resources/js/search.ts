import type { StreetOption } from './api';

export type Suggestion = { street: StreetOption; postcode?: string; ruledOut: boolean };

export type SearchResult = {
    suggestions: Suggestion[];
    total: number;
    /** How many of the total the clues haven't ruled out. */
    possibleTotal: number;
    mode: 'name' | 'postcode';
};

// Enough to scroll through, so a page of ruled-out streets doesn't hide the possible ones.
const LIMIT = 50;

// Postcodes start with one or two letters then a digit ("M6", "WA3"). No Salford street name does.
const LOOKS_LIKE_POSTCODE = /^[a-z]{1,2}\d/i;

const normaliseName = (s: string) => s.toLowerCase().replace(/['’.]/g, '').replace(/\s+/g, ' ').trim();

/**
 * Autocomplete over street names, or over full postcodes when the query looks
 * like one: "M6 7R" lists every street with a postcode starting "M6 7R".
 * Streets the clues have ruled out are flagged and listed after possible ones.
 *
 * @param possible  ids still consistent with the clues; null before any guess
 */
export function searchStreets(
    streets: StreetOption[],
    query: string,
    exclude: Set<number>,
    possible: Set<number> | null = null,
): SearchResult {
    const q = query.trim();

    if (!q) return { suggestions: [], total: 0, possibleTotal: 0, mode: 'name' };

    const mode = LOOKS_LIKE_POSTCODE.test(q) ? 'postcode' : 'name';
    const found = (mode === 'postcode' ? byPostcode : byName)(streets, q, exclude).map((s) => ({
        ...s,
        ruledOut: possible !== null && !possible.has(s.street.id),
    }));

    // Stable: keeps name/prefix order within the possible and ruled-out groups.
    const stillPossible = found.filter((s) => !s.ruledOut);
    const ordered = [...stillPossible, ...found.filter((s) => s.ruledOut)];

    return {
        suggestions: ordered.slice(0, LIMIT),
        total: found.length,
        possibleTotal: stillPossible.length,
        mode,
    };
}

type Match = { street: StreetOption; postcode?: string };

/** Names starting with the query first, then names containing it anywhere. */
function byName(streets: StreetOption[], query: string, exclude: Set<number>): Match[] {
    const q = normaliseName(query);
    const starts: Match[] = [];
    const contains: Match[] = [];

    for (const street of streets) {
        if (exclude.has(street.id)) continue;
        const name = normaliseName(street.name);
        if (name.startsWith(q)) starts.push({ street });
        else if (name.includes(q)) contains.push({ street });
    }

    return [...starts, ...contains];
}

function byPostcode(streets: StreetOption[], query: string, exclude: Set<number>): Match[] {
    const spaced = query.toUpperCase().replace(/\s+/g, ' ');
    const compact = spaced.replace(/ /g, '');

    const found: Match[] = [];

    // A bare district ("M6") means the street's district, the same one the postcode clue and the
    // remaining-postcodes counts use. (Not a prefix, so "M6" doesn't also match M60.)
    if (!spaced.includes(' ') && compact.length <= 4 && streets.some((s) => s.district === compact)) {
        for (const street of streets) {
            if (!exclude.has(street.id) && street.district === compact) found.push({ street, postcode: compact });
        }

        return found;
    }

    // "M6 7R" matches unit postcodes by prefix; a spaceless run like "M67RG" does too once it's long enough.
    const matches = (postcode: string) =>
        spaced.includes(' ')
            ? postcode.startsWith(spaced)
            : compact.length >= 5 && postcode.replace(' ', '').startsWith(compact);

    for (const street of streets) {
        if (exclude.has(street.id)) continue;
        const postcode = street.postcodes.find(matches);
        if (postcode) found.push({ street, postcode });
    }

    return found;
}
