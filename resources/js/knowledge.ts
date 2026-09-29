import type { Guess } from './api';

export type Fact = { label: string; value: string; known: boolean };

/**
 * What the guesses so far say about the answer, one line per clue:
 * "Ward: Eccles" once a guess matched, otherwise "Ward: not Ordsall, Claremont".
 * Direction isn't summarised; the dimmed suggestions already account for it.
 */
export function knowledge(guesses: Guess[]): Fact[] {
    return [
        categorical('Ward', guesses, (g) => [g.street.ward, g.matches.ward]),
        categorical('Postcode', guesses, (g) => [g.street.postcode_district, g.matches.postcode_district]),
        categorical('Type', guesses, (g) => [g.street.street_type, g.matches.street_type]),
        letter(guesses),
    ].filter((fact): fact is Fact => fact !== null);
}

function categorical(label: string, guesses: Guess[], clue: (g: Guess) => [string | null, boolean]): Fact | null {
    const hit = guesses.find((g) => clue(g)[1]);

    if (hit) return { label, value: clue(hit)[0] ?? '?', known: true };

    const ruledOut = [...new Set(guesses.map((g) => clue(g)[0]).filter((v): v is string => v !== null))];

    return ruledOut.length ? { label, value: `not ${ruledOut.join(', ')}`, known: false } : null;
}

function letter(guesses: Guess[]): Fact | null {
    const hit = guesses.find((g) => g.matches.first_letter);

    if (hit) return { label: 'First letter', value: hit.street.first_letter, known: true };

    const after = guesses.filter((g) => g.first_letter_hint === 'after').map((g) => g.street.first_letter).sort().at(-1);
    const before = guesses.filter((g) => g.first_letter_hint === 'before').map((g) => g.street.first_letter).sort().at(0);

    const value = after && before ? `between ${after} and ${before}` : after ? `after ${after}` : before ? `before ${before}` : null;

    return value ? { label: 'First letter', value, known: false } : null;
}
