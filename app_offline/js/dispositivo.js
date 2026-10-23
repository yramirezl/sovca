// Gestión de la identidad del dispositivo: un UUID v4 estable y un
// nombre legible, ambos guardados en localStorage. Se generan la
// primera vez y se reutilizan en cada sesión.
//
// El UUID es lo que se registra en el servidor (tabla
// usuario_dispositivo_offline.dispositivo_id). Si el usuario borra
// localStorage (limpieza de caché, cambio de navegador, formateo),
// el UUID cambia y el servidor lo va a tratar como un dispositivo
// nuevo. Eso es intencional: borrar caché implica "resetear" el
// dispositivo, y hay que revincularlo.
//
// El nombre legible es solo para que la web muestre algo humano en la
// pantalla "Dispositivos offline vinculados" (ej. "Chrome en Windows
// — a1b2"). No se usa para identificar nada.
const Dispositivo = {
  ID_KEY: 'bovs_dispositivo_id',
  NOMBRE_KEY: 'bovs_nombre_dispositivo',

  /**
   * Devuelve el UUID del dispositivo, generándolo la primera vez.
   * Valida que el guardado tenga formato UUID v4 correcto — si está
   * corrupto o es de un formato viejo, se regenera (mejor un UUID
   * nuevo que uno inválido que el server rechace).
   */
  obtenerId() {
    let id = null;
    try {
      id = localStorage.getItem(this.ID_KEY);
    } catch (e) {
      // localStorage bloqueado (modo privado extremo, políticas del
      // navegador). Se genera uno efímero que vive solo en esta
      // sesión — el vínculo del servidor va a quedar pero el cliente
      // no podrá reutilizarlo. Es el mejor fallback disponible.
      return this._generarUuid();
    }

    if (id && this._esUuidValido(id)) return id;

    id = this._generarUuid();
    try {
      localStorage.setItem(this.ID_KEY, id);
    } catch (e) { /* idem: fallback efímero */ }
    return id;
  },

  /**
   * Nombre legible para mostrar en la UI del servidor. Se genera una
   * sola vez y se reutiliza. A diferencia del UUID, si el usuario
   * borra localStorage no pasa nada grave: se regenera con el mismo
   * formato y el vínculo sigue funcionando por UUID.
   */
  obtenerNombre() {
    let guardado = null;
    try {
      guardado = localStorage.getItem(this.NOMBRE_KEY);
    } catch (e) { /* sin localStorage, se genera uno efímero */ }

    if (guardado) return guardado;

    const generado = this._generarNombre();
    try {
      localStorage.setItem(this.NOMBRE_KEY, generado);
    } catch (e) { /* efímero */ }
    return generado;
  },

  _generarUuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = (Math.random() * 16) | 0;
      const v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  },

  _esUuidValido(uuid) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(uuid);
  },

  _generarNombre() {
    const ua = navigator.userAgent || '';
    let so = 'Dispositivo';
    if (/Windows/i.test(ua)) so = 'Windows';
    else if (/Android/i.test(ua)) so = 'Android';
    else if (/iPhone|iPad|iPod/i.test(ua)) so = 'iOS';
    else if (/Macintosh|Mac OS/i.test(ua)) so = 'Mac';
    else if (/Linux/i.test(ua)) so = 'Linux';

    let navegador = 'navegador';
    if (/Edg\//i.test(ua)) navegador = 'Edge';
    else if (/OPR\/|Opera/i.test(ua)) navegador = 'Opera';
    else if (/Chrome\//i.test(ua) && !/Edg\//i.test(ua)) navegador = 'Chrome';
    else if (/Firefox\//i.test(ua)) navegador = 'Firefox';
    else if (/Safari\//i.test(ua) && !/Chrome\//i.test(ua)) navegador = 'Safari';

    const sufijo = Math.random().toString(16).slice(2, 6);
    return navegador + ' en ' + so + ' — ' + sufijo;
  },
};