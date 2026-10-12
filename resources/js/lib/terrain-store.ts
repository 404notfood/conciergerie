export type EncryptedRecord = { iv: Uint8Array; data: ArrayBuffer };
export type VaultMeta = {
    salt: Uint8Array;
    identity: string;
    preparedAt: string;
};
const dbName = 'tout-en-main-terrain-v1';
async function database(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(dbName, 1);
        request.onupgradeneeded = () =>
            request.result.createObjectStore('vault');
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}
export async function readVault<T>(id: string): Promise<T | undefined> {
    const db = await database();
    return new Promise((resolve, reject) => {
        const tx = db.transaction('vault', 'readonly');
        const request = tx.objectStore('vault').get(id);
        let result: T | undefined;
        request.onsuccess = () => {
            result = request.result as T | undefined;
        };
        tx.oncomplete = () => {
            db.close();
            resolve(result);
        };
        tx.onerror = () => {
            db.close();
            reject(tx.error);
        };
    });
}
export async function writeVault(entries: [string, unknown][]): Promise<void> {
    const db = await database();
    return new Promise((resolve, reject) => {
        const tx = db.transaction('vault', 'readwrite');
        for (const [id, value] of entries)
            tx.objectStore('vault').put(value, id);
        tx.oncomplete = () => {
            db.close();
            resolve();
        };
        tx.onerror = () => {
            db.close();
            reject(tx.error);
        };
        tx.onabort = () => {
            db.close();
            reject(tx.error ?? new Error('Écriture locale interrompue.'));
        };
    });
}
export async function deriveKey(
    pin: string,
    salt: Uint8Array,
): Promise<CryptoKey> {
    const base = await crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(pin),
        'PBKDF2',
        false,
        ['deriveKey'],
    );
    return crypto.subtle.deriveKey(
        {
            name: 'PBKDF2',
            salt: salt as BufferSource,
            iterations: 210000,
            hash: 'SHA-256',
        },
        base,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
    );
}
export async function encrypt<T>(
    key: CryptoKey,
    value: T,
): Promise<EncryptedRecord> {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const data = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        key,
        new TextEncoder().encode(JSON.stringify(value)),
    );
    return { iv, data };
}
export async function decrypt<T>(
    key: CryptoKey,
    value: EncryptedRecord,
): Promise<T> {
    const data = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: value.iv as BufferSource },
        key,
        value.data,
    );
    return JSON.parse(new TextDecoder().decode(data)) as T;
}
export function fileAsDataUrl(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result));
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(file);
    });
}
export function dataUrlBlob(url: string): Blob {
    const [header, base64] = url.split(',');
    const data = Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
    return new Blob([data], { type: header.split(':')[1].split(';')[0] });
}
