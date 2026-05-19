import { router } from '@inertiajs/vue3';

export type ApiErrors = Record<string, string>;

export type ApiRequestOptions<TResponse> = {
    onSuccess?: (data: TResponse) => void;
    onError?: (errors: ApiErrors) => void;
    onFinish?: () => void;
};

type HttpMethod = 'POST' | 'PUT' | 'PATCH' | 'DELETE';

type ValidationErrorBody = {
    message?: string;
    errors?: Record<string, string[] | string>;
};

function readXsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    if (!match) {
        return '';
    }

    return decodeURIComponent(match.split('=')[1] ?? '');
}

function buildHeaders(body: unknown): HeadersInit {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': readXsrfToken(),
    };

    if (!(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    return headers;
}

function flattenLaravelErrors(body: ValidationErrorBody): ApiErrors {
    const errors: ApiErrors = {};
    const raw = body.errors ?? {};

    for (const [field, value] of Object.entries(raw)) {
        if (Array.isArray(value)) {
            errors[field] = value[0] ?? '';
            continue;
        }

        errors[field] = value;
    }

    if (Object.keys(errors).length === 0 && body.message) {
        errors._global = body.message;
    }

    return errors;
}

function serializeBody(body: unknown): BodyInit | null {
    if (body === undefined || body === null) {
        return null;
    }

    if (body instanceof FormData) {
        return body;
    }

    return JSON.stringify(body);
}

async function request<TResponse>(
    method: HttpMethod,
    url: string,
    body: unknown,
    options: ApiRequestOptions<TResponse> = {},
): Promise<TResponse | null> {
    try {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: buildHeaders(body),
            body: serializeBody(body),
        });

        if (response.status === 401 || response.status === 419) {
            router.visit('/login');

            return null;
        }

        if (response.status === 403) {
            options.onError?.({
                _global: 'No tenés permisos para esta acción.',
            });

            return null;
        }

        if (response.status === 204) {
            const data = null as TResponse;
            options.onSuccess?.(data);

            return data;
        }

        const json = (await response.json().catch(() => ({}))) as
            | TResponse
            | ValidationErrorBody;

        if (!response.ok) {
            const errors =
                response.status === 422
                    ? flattenLaravelErrors(json as ValidationErrorBody)
                    : {
                          _global:
                              (json as ValidationErrorBody).message ??
                              'Error inesperado.',
                      };
            options.onError?.(errors);

            return null;
        }

        options.onSuccess?.(json as TResponse);

        return json as TResponse;
    } catch {
        options.onError?.({ _global: 'Error de conexión.' });

        return null;
    } finally {
        options.onFinish?.();
    }
}

export type ApiClient = {
    post: <TResponse = unknown>(
        url: string,
        body?: unknown,
        options?: ApiRequestOptions<TResponse>,
    ) => Promise<TResponse | null>;
    put: <TResponse = unknown>(
        url: string,
        body?: unknown,
        options?: ApiRequestOptions<TResponse>,
    ) => Promise<TResponse | null>;
    patch: <TResponse = unknown>(
        url: string,
        body?: unknown,
        options?: ApiRequestOptions<TResponse>,
    ) => Promise<TResponse | null>;
    delete: <TResponse = unknown>(
        url: string,
        options?: ApiRequestOptions<TResponse>,
    ) => Promise<TResponse | null>;
};

export function useApi(): ApiClient {
    return {
        post: (url, body, options) => request('POST', url, body, options),
        put: (url, body, options) => request('PUT', url, body, options),
        patch: (url, body, options) => request('PATCH', url, body, options),
        delete: (url, options) => request('DELETE', url, undefined, options),
    };
}
