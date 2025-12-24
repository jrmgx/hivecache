/**
 * Custom error class for API errors that includes HTTP status code
 */

export class ApiError extends Error {
  constructor(message: string, public status: number) {
    super(message);
    this.name = 'ApiError';
  }
}

