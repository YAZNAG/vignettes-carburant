import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import api from './api';

const AuthContexte = createContext(null);

export function AuthProvider({ children }) {
  const [utilisateur, setUtilisateur] = useState(null);
  const [permissions, setPermissions] = useState([]);
  const [session, setSession] = useState(null);
  const [chargement, setChargement] = useState(true);

  const appliquer = useCallback((donnees) => {
    setUtilisateur(donnees?.utilisateur || null);
    setPermissions(donnees?.permissions || []);
    setSession(donnees?.session || null);
  }, []);

  const recharger = useCallback(async () => {
    try {
      const reponse = await api.get('/api/auth/me');
      appliquer(reponse.data);
      return reponse.data;
    } catch {
      appliquer(null);
      return null;
    }
  }, [appliquer]);

  useEffect(() => {
    (async () => {
      await recharger();
      setChargement(false);
    })();
  }, [recharger]);

  const deconnecter = useCallback(async () => {
    try {
      await api.post('/api/auth/logout');
    } catch {
      // La session est peut-être déjà expirée : on poursuit la déconnexion locale.
    }
    appliquer(null);
    window.location.href = '/connexion';
  }, [appliquer]);

  const peut = useCallback(
    (code) => permissions.includes(code),
    [permissions]
  );

  const valeur = useMemo(
    () => ({ utilisateur, permissions, session, chargement, peut, recharger, deconnecter, appliquer }),
    [utilisateur, permissions, session, chargement, peut, recharger, deconnecter, appliquer]
  );

  return <AuthContexte.Provider value={valeur}>{children}</AuthContexte.Provider>;
}

export function useAuth() {
  return useContext(AuthContexte);
}
