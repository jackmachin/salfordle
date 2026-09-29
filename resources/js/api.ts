export type Matches = {
    ward: boolean;
    postcode_district: boolean;
    street_type: boolean;
    first_letter: boolean;
};

export type GuessedStreet = {
    id: number;
    name: string;
    ward: string | null;
    postcode_district: string | null;
    street_type: string | null;
    first_letter: string;
};

export type Guess = {
    number: number;
    street: GuessedStreet;
    correct: boolean;
    matches: Matches;
    /** Where the answer's first letter sits relative to this guess's, when they differ. */
    first_letter_hint: 'before' | 'after' | null;
    distance_miles: number;
    direction: string | null;
};

export type GameState = {
    puzzle: { number: number; date: string; image_url: string };
    max_guesses: number;
    total_streets: number;
    remaining: number;
    /** Ids of streets still consistent with every clue. */
    candidates: number[];
    /** Postcode districts still possible, with how many candidates each. */
    remaining_postcode_districts: Record<string, number>;
    status: 'playing' | 'won' | 'lost';
    guesses: Guess[];
    answer: null | {
        id: number;
        name: string;
        ward: string | null;
        postcode_district: string | null;
        street_type: string | null;
        sub_area: string | null;
    };
};

export type StreetOption = { id: number; name: string; district: string | null; postcodes: string[] };

export class ApiError extends Error {
    constructor(
        public status: number,
        message: string,
    ) {
        super(message);
    }
}

async function request<T>(url: string, init: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...init,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...init.headers },
    });

    const body = await response.json().catch(() => null);

    if (!response.ok) {
        const validation = body?.errors ? Object.values(body.errors as Record<string, string[]>).flat()[0] : null;
        throw new ApiError(response.status, validation ?? body?.message ?? 'Something went wrong. Please try again.');
    }

    return body as T;
}

export const api = {
    puzzle: () => request<GameState>('/api/puzzle'),
    // Always revalidate: a copy cached before a data change would break the search. Unchanged lists are a cheap 304.
    streets: () => request<StreetOption[]>('/api/streets', { cache: 'no-cache' }),
    guess: (streetId: number, puzzle: number) =>
        request<GameState>('/api/guesses', {
            method: 'POST',
            body: JSON.stringify({ street_id: streetId, puzzle }),
        }),

    /** The image comes back as JSON with a message when it's unavailable (e.g. the daily cap). */
    async image(url: string): Promise<string> {
        const response = await fetch(url, { credentials: 'same-origin' });

        if (!response.ok) {
            const body = await response.json().catch(() => null);
            throw new ApiError(response.status, body?.message ?? 'The Street View image is unavailable right now.');
        }

        return URL.createObjectURL(await response.blob());
    },
};
