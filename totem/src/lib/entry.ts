/**
 * Converte o link por caminho do QR (`/qualquer/base/{code}?t=token`, sem
 * hash) na rota de entrada. Devolve `null` quando não é um link de QR.
 */
export function entryFromLocation(
    pathname: string,
    search: string,
): string | null {
    const token = new URLSearchParams(search).get('t');
    const code = /\/([\w-]+)\/?$/.exec(pathname)?.[1];

    if (!token || !code) {
        return null;
    }

    return `/t/${code}?t=${encodeURIComponent(token)}`;
}
