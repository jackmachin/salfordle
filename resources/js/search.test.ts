import { describe, expect, it } from 'vitest';
import { searchStreets } from './search';

const streets = [
    { id: 1, name: 'Church Street (Eccles)', district: 'M30', postcodes: ['M30 0DF'] },
    { id: 2, name: 'Duffield Road', district: 'M6', postcodes: ['M6 7RB', 'M6 7RD'] },
    { id: 3, name: 'Helena Street', district: 'M6', postcodes: ['M6 7RG', 'M6 7RP'] },
    { id: 4, name: 'Old Church Lane', district: 'M60', postcodes: ['M60 1AA'] },
    { id: 5, name: "St John's Road", district: 'M6', postcodes: ['M6 8AB'] },
    { id: 6, name: 'No Postcode Close', district: 'M7', postcodes: [] },
];

const ids = (query: string, exclude = new Set<number>()) =>
    searchStreets(streets, query, exclude).suggestions.map((s) => s.street.id);

describe('searchStreets by name', () => {
    it('puts names starting with the query before names containing it', () => {
        expect(ids('church')).toEqual([1, 4]);
    });

    it('ignores case, apostrophes and full stops', () => {
        expect(ids('st johns')).toEqual([5]);
        expect(ids("ST. JOHN'S")).toEqual([5]);
    });

    it('leaves out streets already guessed', () => {
        expect(ids('church', new Set([1]))).toEqual([4]);
    });
});

describe('searchStreets by postcode', () => {
    it('matches a partial unit postcode by prefix and says which postcode matched', () => {
        const result = searchStreets(streets, 'm6 7r', new Set());

        expect(result.mode).toBe('postcode');
        expect(result.suggestions).toEqual([
            { street: streets[1], postcode: 'M6 7RB', ruledOut: false },
            { street: streets[2], postcode: 'M6 7RG', ruledOut: false },
        ]);
    });

    it('treats a bare district as the whole district, not a prefix of longer ones', () => {
        expect(ids('M6')).toEqual([2, 3, 5]);
        expect(ids('M6 ')).toEqual([2, 3, 5]);
    });

    it('matches a bare district by the district of the street, even without a unit postcode', () => {
        // No Postcode Close has no unit postcodes, but its district is M7.
        expect(ids('M7')).toEqual([6]);
    });

    it('accepts a full postcode without the space', () => {
        expect(ids('M67RG')).toEqual([3]);
    });

    it('extra spaces do not matter', () => {
        expect(ids('  M6   7RD ')).toEqual([2]);
    });
});

describe('searchStreets with clues', () => {
    it('flags ruled-out streets and lists them after possible ones', () => {
        const result = searchStreets(streets, 'M6', new Set(), new Set([3]));

        expect(result.suggestions.map((s) => [s.street.id, s.ruledOut])).toEqual([
            [3, false],
            [2, true],
            [5, true],
        ]);
        expect(result.total).toBe(3);
        expect(result.possibleTotal).toBe(1);
    });

    it('flags nothing before the first guess', () => {
        expect(searchStreets(streets, 'church', new Set()).suggestions.every((s) => !s.ruledOut)).toBe(true);
    });
});

describe('searchStreets result limits', () => {
    it('lists a still-possible street even behind many ruled-out ones', () => {
        const roads = Array.from({ length: 20 }, (_, i) => ({ id: 100 + i, name: `Abc${i} Road`, district: null, postcodes: [] }));
        const zRoad = { id: 999, name: 'Zulu Road', district: null, postcodes: [] };

        const result = searchStreets([...roads, zRoad], 'road', new Set(), new Set([999]));

        expect(result.suggestions[0].street.id).toBe(999);
        expect(result.suggestions).toHaveLength(21);
        expect(result.possibleTotal).toBe(1);
    });

    it('caps long lists at 50 but reports the full count', () => {
        const many = Array.from({ length: 80 }, (_, i) => ({ id: i, name: `Street ${i} Road`, district: null, postcodes: [] }));
        const result = searchStreets(many, 'road', new Set());

        expect(result.suggestions).toHaveLength(50);
        expect(result.total).toBe(80);
    });
});
