import type { GameState } from './api';
import { ARROWS } from './components/GuessGrid';

/**
 * Spoiler-free result, one row per guess, squares in a fixed order:
 * ward, postcode district, street type, first letter.
 *
 *   Salfordle #47 - 4/6
 *
 *   🟩🟩⬜🟩 ↗️ 4.2mi
 *   🟩🟩🟩🟩 🎯 SOLVED
 */
export function shareText(game: GameState, url?: string): string {
    const score = game.status === 'won' ? game.guesses.length : 'X';

    const rows = game.guesses.map((guess) => {
        const { ward, postcode_district, street_type, first_letter } = guess.matches;
        const squares = [ward, postcode_district, street_type, first_letter].map((m) => (m ? '🟩' : '⬜')).join('');

        return guess.correct
            ? `${squares} 🎯 SOLVED`
            : `${squares} ${ARROWS[guess.direction ?? ''] ?? ''} ${guess.distance_miles.toFixed(1)}mi`;
    });

    return [`Salfordle #${game.puzzle.number} - ${score}/${game.max_guesses}`, '', ...rows, ...(url ? ['', url] : [])].join('\n');
}
