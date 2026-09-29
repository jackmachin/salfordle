import { describe, expect, it } from 'vitest';
import type { GameState, Guess } from './api';
import { shareText } from './share';

const street = { id: 1, name: 'Somewhere Road', ward: 'Eccles', postcode_district: 'M30', street_type: 'Road', first_letter: 'S' };

function guess(number: number, overrides: Partial<Guess>): Guess {
    return {
        number,
        street,
        correct: false,
        matches: { ward: true, postcode_district: true, street_type: false, first_letter: true },
        first_letter_hint: null,
        distance_miles: 4.2,
        direction: 'NE',
        ...overrides,
    };
}

function game(status: GameState['status'], guesses: Guess[]): GameState {
    return {
        puzzle: { number: 47, date: '2026-10-01', image_url: '/api/puzzle/image' },
        max_guesses: 6,
        total_streets: 3645,
        remaining: 1,
        candidates: [1],
        remaining_postcode_districts: { M30: 1 },
        status,
        guesses,
        answer: null,
    };
}

describe('shareText', () => {
    it('renders the squares in fixed order, with direction and distance, ending in the solve', () => {
        const text = shareText(
            game('won', [
                guess(1, {}),
                guess(2, { matches: { ward: true, postcode_district: true, street_type: true, first_letter: true }, distance_miles: 1.75, direction: 'SE' }),
                guess(3, { correct: true, matches: { ward: true, postcode_district: true, street_type: true, first_letter: true }, distance_miles: 0, direction: null }),
            ]),
        );

        expect(text).toBe(['Salfordle #47 - 3/6', '', '🟩🟩⬜🟩 ↗️ 4.2mi', '🟩🟩🟩🟩 ↘️ 1.8mi', '🟩🟩🟩🟩 🎯 SOLVED'].join('\n'));
    });

    it('scores a loss as X and appends the link when given', () => {
        const text = shareText(game('lost', Array.from({ length: 6 }, (_, i) => guess(i + 1, {}))), 'https://streetle.example');

        expect(text.split('\n')[0]).toBe('Salfordle #47 - X/6');
        expect(text.split('\n')).toHaveLength(2 + 6 + 2);
        expect(text.endsWith('\n\nhttps://streetle.example')).toBe(true);
    });

    it('never includes a street name', () => {
        expect(shareText(game('won', [guess(1, { correct: true, direction: null })]))).not.toContain('Somewhere');
    });
});
