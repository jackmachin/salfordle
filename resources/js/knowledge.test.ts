import { describe, expect, it } from 'vitest';
import type { Guess } from './api';
import { knowledge } from './knowledge';

function guess(
    street: { ward: string; postcode: string; type: string; letter: string },
    matches: [boolean, boolean, boolean, boolean],
    hint: Guess['first_letter_hint'] = null,
): Guess {
    return {
        number: 1,
        street: { id: 1, name: 'x', ward: street.ward, postcode_district: street.postcode, street_type: street.type, first_letter: street.letter },
        correct: false,
        matches: { ward: matches[0], postcode_district: matches[1], street_type: matches[2], first_letter: matches[3] },
        first_letter_hint: hint,
        distance_miles: 1,
        direction: 'N',
    };
}

describe('knowledge', () => {
    it('is empty before any guess', () => {
        expect(knowledge([])).toEqual([]);
    });

    it('lists ruled-out values until one matches', () => {
        const facts = knowledge([
            guess({ ward: 'Eccles', postcode: 'M30', type: 'Road', letter: 'C' }, [false, false, true, false], 'after'),
            guess({ ward: 'Ordsall', postcode: 'M5', type: 'Street', letter: 'T' }, [false, true, false, false], 'before'),
            guess({ ward: 'Eccles', postcode: 'M5', type: 'Road', letter: 'F' }, [false, true, true, false], 'after'),
        ]);

        expect(facts).toEqual([
            { label: 'Ward', value: 'not Eccles, Ordsall', known: false },
            { label: 'Postcode', value: 'M5', known: true },
            { label: 'Type', value: 'Road', known: true },
            { label: 'First letter', value: 'between F and T', known: false },
        ]);
    });

    it('narrows the letter from one side only', () => {
        const facts = knowledge([guess({ ward: 'A', postcode: 'M1', type: 'Road', letter: 'M' }, [false, false, false, false], 'before')]);

        expect(facts.find((f) => f.label === 'First letter')?.value).toBe('before M');
    });
});
