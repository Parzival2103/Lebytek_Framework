/*
|--------------------------------------------------------------------------
| avatar-manager.js — Gestión AJAX del avatar (partial avatar_manager)
|--------------------------------------------------------------------------
| Se inicializa sobre cada [data-avatar-manager]. Todo por fetch, sin
| recargas: subir (POST multipart), fijar (POST {id}/actual), eliminar
| (POST + _method=DELETE) con ConfirmModal global. CSRF por header
| X-CSRF-Token leído del data-csrf del contenedor.
*/
(() => {
  'use strict';

  function init(root) {
    const baseUrl = root.dataset.baseUrl;
    const csrf = root.dataset.csrf;
    const main = root.querySelector('[data-avatar-main]');
    const gallery = root.querySelector('[data-avatar-gallery]');
    const input = root.querySelector('[data-avatar-input]');
    const errorBox = root.querySelector('[data-avatar-error]');
    const spinner = root.querySelector('[data-avatar-spinner]');

    function showError(message) {
      if (!errorBox) return;
      errorBox.textContent = message;
      errorBox.classList.remove('d-none');
    }

    function clearError() {
      if (!errorBox) return;
      errorBox.textContent = '';
      errorBox.classList.add('d-none');
    }

    function setBusy(busy) {
      if (spinner) spinner.classList.toggle('d-none', !busy);
      if (input) input.disabled = busy;
    }

    async function request(url, options) {
      const response = await fetch(url, Object.assign({
        headers: { 'X-CSRF-Token': csrf },
        credentials: 'same-origin',
      }, options));
      let data = null;
      try {
        data = await response.json();
      } catch (e) {
        throw new Error('Respuesta inesperada del servidor.');
      }
      if (!data || data.ok !== true) {
        throw new Error((data && data.error) || 'No fue posible completar la operación.');
      }
      return data;
    }

    function render(payload) {
      // Principal: imagen actual con burbuja X, o recuadro vacío.
      if (payload.actual) {
        main.innerHTML =
          '<img src="' + escapeAttr(payload.actual.ruta) + '" alt="Avatar actual" ' +
          'class="rounded-3 object-fit-cover border" width="140" height="140">' +
          '<button type="button" class="btn btn-danger btn-sm rounded-circle position-absolute ' +
          'top-0 start-100 translate-middle p-0 d-flex align-items-center justify-content-center" ' +
          'style="width: 1.6rem; height: 1.6rem;" data-avatar-delete="' + payload.actual.id + '" ' +
          'aria-label="Eliminar foto de perfil"><i class="bi bi-x-lg small"></i></button>';
      } else {
        main.innerHTML =
          '<div class="rounded-3 border border-2 border-dashed d-flex flex-column align-items-center ' +
          'justify-content-center text-secondary bg-body-tertiary" style="width: 140px; height: 140px;">' +
          '<i class="bi bi-person fs-1"></i><span class="small">Sin foto de perfil</span></div>';
      }

      // Galería: miniaturas, la actual resaltada (estado desde la respuesta).
      if (payload.historial.length === 0) {
        gallery.innerHTML = '<span class="text-secondary small" data-avatar-empty>Sin fotos anteriores.</span>';
        return;
      }
      gallery.innerHTML = payload.historial.map((item) =>
        '<button type="button" class="btn p-0 border rounded-2 overflow-hidden ' +
        (item.esActual ? 'border-primary border-2' : '') + '" data-avatar-pick="' + item.id + '"' +
        (item.esActual ? ' data-avatar-current' : '') + ' aria-label="Usar esta foto">' +
        '<img src="' + escapeAttr(item.ruta) + '" alt="" width="56" height="56" class="object-fit-cover d-block">' +
        '</button>'
      ).join('');
    }

    function escapeAttr(value) {
      return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    async function subir(file) {
      clearError();
      setBusy(true);
      try {
        const body = new FormData();
        body.append('avatar', file);
        render(await request(baseUrl, { method: 'POST', body }));
      } catch (e) {
        showError(e.message);
      } finally {
        setBusy(false);
        if (input) input.value = '';
      }
    }

    async function fijar(id) {
      clearError();
      try {
        render(await request(baseUrl + '/' + id + '/actual', { method: 'POST' }));
      } catch (e) {
        showError(e.message);
      }
    }

    async function eliminar(id) {
      const ok = await window.ConfirmModal.show({
        variant: 'danger',
        title: 'Eliminar foto de perfil',
        body: 'La foto pasará al historial de eliminadas y dejará de mostrarse. ¿Continuar?',
        ok: 'Eliminar',
      });
      if (!ok) return;

      clearError();
      try {
        const body = new FormData();
        body.append('_method', 'DELETE');
        render(await request(baseUrl + '/' + id, { method: 'POST', body }));
      } catch (e) {
        showError(e.message);
      }
    }

    input?.addEventListener('change', () => {
      if (input.files && input.files[0]) subir(input.files[0]);
    });

    root.addEventListener('click', (event) => {
      const del = event.target.closest('[data-avatar-delete]');
      if (del) {
        eliminar(del.dataset.avatarDelete);
        return;
      }
      const pick = event.target.closest('[data-avatar-pick]');
      if (pick && !pick.hasAttribute('data-avatar-current')) {
        fijar(pick.dataset.avatarPick);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-avatar-manager]').forEach(init);
  });
})();
