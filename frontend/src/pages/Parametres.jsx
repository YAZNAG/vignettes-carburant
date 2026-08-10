import { useCallback, useEffect, useState } from 'react';
import api, { messageErreur } from '../api';
import { useAuth } from '../AuthContext';

export default function Parametres() {
  const { peut } = useAuth();
  const [parametres, setParametres] = useState([]);
  const [rolesTotp, setRolesTotp] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');
  const [enregistrement, setEnregistrement] = useState(false);

  const peutModifier = peut('parametre.modifier');

  const charger = useCallback(async () => {
    setChargement(true);
    setErreur('');
    try {
      const reponse = await api.get('/api/parametres');
      setParametres(reponse.data?.parametres || []);
      setRolesTotp(reponse.data?.roles_totp || []);
    } catch (err) {
      setErreur(messageErreur(err, 'Impossible de charger les paramètres.'));
    } finally {
      setChargement(false);
    }
  }, []);

  useEffect(() => {
    charger();
  }, [charger]);

  const definirValeur = (cle, valeur) => {
    setParametres((liste) => liste.map((p) => (p.cle === cle ? { ...p, valeur } : p)));
  };

  const enregistrer = async () => {
    setErreur('');
    setSucces('');
    setEnregistrement(true);
    try {
      await api.put('/api/parametres', {
        parametres: parametres.map((p) => ({ cle: p.cle, valeur: p.valeur })),
      });
      setSucces('Les paramètres ont été enregistrés.');
    } catch (err) {
      setErreur(messageErreur(err, "L'enregistrement a échoué."));
    } finally {
      setEnregistrement(false);
    }
  };

  const basculerTotp = async (role) => {
    setErreur('');
    setSucces('');
    try {
      await api.put(`/api/roles/${role.id}/totp-obligatoire`, {
        totp_obligatoire: !role.totp_obligatoire,
      });
      setRolesTotp((liste) =>
        liste.map((r) => (r.id === role.id ? { ...r, totp_obligatoire: !r.totp_obligatoire } : r))
      );
    } catch (err) {
      setErreur(messageErreur(err, 'La mise à jour du rôle a échoué.'));
    }
  };

  if (chargement) {
    return <div className="chargement">Chargement…</div>;
  }

  return (
    <div>
      <h1>Paramètres</h1>

      {erreur && <div className="alerte alerte-erreur">{erreur}</div>}
      {succes && <div className="alerte alerte-succes">{succes}</div>}

      <div className="carte">
        <h2>Paramètres généraux</h2>
        <div className="grille-formulaire">
          {parametres.map((parametre) => (
            <div key={parametre.cle} className="champ">
              <label htmlFor={`parametre-${parametre.cle}`}>{parametre.libelle || parametre.cle}</label>
              <input
                id={`parametre-${parametre.cle}`}
                type="text"
                value={parametre.valeur ?? ''}
                disabled={!peutModifier}
                onChange={(e) => definirValeur(parametre.cle, e.target.value)}
              />
            </div>
          ))}
        </div>
        {peutModifier && (
          <button type="button" className="btn" onClick={enregistrer} disabled={enregistrement}>
            {enregistrement ? 'Enregistrement…' : 'Enregistrer les paramètres'}
          </button>
        )}
      </div>

      <div className="carte">
        <h2>Double authentification (2FA) obligatoire par rôle</h2>
        <div className="conteneur-tableau">
          <table className="tableau">
            <thead>
              <tr>
                <th>Rôle</th>
                <th>2FA obligatoire</th>
              </tr>
            </thead>
            <tbody>
              {rolesTotp.map((role) => (
                <tr key={role.id}>
                  <td>{role.libelle}</td>
                  <td>
                    <div className="champ-case">
                      <input
                        id={`totp-${role.id}`}
                        type="checkbox"
                        checked={!!role.totp_obligatoire}
                        disabled={!peutModifier}
                        onChange={() => basculerTotp(role)}
                      />
                      <label htmlFor={`totp-${role.id}`}>
                        {role.totp_obligatoire ? 'Obligatoire' : 'Facultative'}
                      </label>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
