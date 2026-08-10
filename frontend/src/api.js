import axios from 'axios';

const PAGES_PUBLIQUES = ['/connexion', '/mot-de-passe-oublie', '/reinitialiser-mot-de-passe'];

const api = axios.create({
  baseURL: 'http://localhost:8000',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

let csrfInitialise = false;

/** Récupère le cookie CSRF de Sanctum avant la première requête mutative. */
export async function assurerCsrf() {
  if (!csrfInitialise) {
    await api.get('/sanctum/csrf-cookie');
    csrfInitialise = true;
  }
}

api.interceptors.request.use(async (config) => {
  const methode = (config.method || 'get').toLowerCase();
  if (['post', 'put', 'patch', 'delete'].includes(methode)) {
    await assurerCsrf();
  }
  return config;
});

// Abonné notifié à chaque réponse API réussie (réinitialisation de la minuterie de session).
let notifierActivite = null;
export function surActiviteApi(fn) {
  notifierActivite = fn;
}

api.interceptors.response.use(
  (reponse) => {
    if (notifierActivite) notifierActivite();
    return reponse;
  },
  (erreur) => {
    const statut = erreur.response?.status;
    const chemin = window.location.pathname;
    if (statut === 401) {
      csrfInitialise = false;
      if (!PAGES_PUBLIQUES.includes(chemin)) {
        window.location.href = '/connexion';
      }
    } else if (statut === 403 && erreur.response?.data?.code === 'MDP_A_CHANGER') {
      if (chemin !== '/changer-mot-de-passe') {
        window.location.href = '/changer-mot-de-passe';
      }
    }
    return Promise.reject(erreur);
  }
);

/** Télécharge un fichier (blob) renvoyé par l'API. */
export async function telechargerBlob(url, params, nomFichier) {
  const reponse = await api.get(url, { params, responseType: 'blob' });
  const disposition = reponse.headers['content-disposition'] || '';
  const correspondance = disposition.match(/filename\*?=(?:UTF-8'')?"?([^;"]+)"?/i);
  const nom = correspondance ? decodeURIComponent(correspondance[1]) : nomFichier;
  const lien = document.createElement('a');
  lien.href = URL.createObjectURL(reponse.data);
  lien.download = nom;
  document.body.appendChild(lien);
  lien.click();
  lien.remove();
  URL.revokeObjectURL(lien.href);
}

/** Extrait un message d'erreur lisible d'une erreur axios. */
export function messageErreur(erreur, defaut = 'Une erreur est survenue.') {
  return erreur?.response?.data?.message || defaut;
}

/** Extrait les erreurs de validation Laravel ({champ: [messages]}). */
export function erreursValidation(erreur) {
  if (erreur?.response?.status === 422) {
    return erreur.response.data?.errors || {};
  }
  return {};
}

export default api;
