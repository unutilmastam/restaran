import { ApiError, getLocale, type ApiEnvelope } from '@sr/shared';

import { readToken } from './auth';

/**
 * Fayl yuklash — `XMLHttpRequest`, `fetch` EMAS.
 *
 * SABAB: `fetch` yuklash (upload) jarayonini kuzatishga imkon bermaydi.
 * Restoran egasi telefondan sekin internetda rasm yuklaydi va progress
 * ko'rmasa, ilova qotib qolgandek tuyuladi.
 */
export function uploadFile<T>(
  path: string,
  file: File,
  field: string,
  onProgress?: (percent: number) => void,
): Promise<T> {
  return new Promise((resolve, reject) => {
    const request = new XMLHttpRequest();
    const form = new FormData();
    form.append(field, file);

    request.open('POST', (import.meta.env.VITE_API_URL ?? '/api/v1') + path);
    request.setRequestHeader('Accept', 'application/json');
    request.setRequestHeader('Accept-Language', getLocale());

    const token = readToken();
    if (token !== null) request.setRequestHeader('Authorization', `Bearer ${token}`);

    request.upload.addEventListener('progress', (event) => {
      if (event.lengthComputable) {
        onProgress?.(Math.round((event.loaded / event.total) * 100));
      }
    });

    request.addEventListener('load', () => {
      let envelope: ApiEnvelope<T> | null = null;

      try {
        envelope = JSON.parse(request.responseText) as ApiEnvelope<T>;
      } catch {
        envelope = null;
      }

      if (envelope === null) {
        reject(new ApiError('SERVER_ERROR', request.status, '', ''));

        return;
      }

      if (envelope.success) {
        resolve(envelope.data);

        return;
      }

      reject(ApiError.fromEnvelope(envelope, request.status));
    });

    request.addEventListener('error', () => {
      reject(new ApiError('NETWORK_ERROR', 0, '', ''));
    });

    request.send(form);
  });
}
