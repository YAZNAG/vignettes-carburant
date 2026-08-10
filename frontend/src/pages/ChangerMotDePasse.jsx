import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api, { erreursValidation, messageErreur } from '../api';
import { useAuth } from '../AuthContext';

export default function ChangerMotDePasse() {
  const { utilisateur, recharger, deconnecter } = useAuth();
  const naviguer = useNavigate();

  const [actuel, setActuel] = useState('');
  const [nouveau, setNouveau] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [erreur, setErreur] = useState('');
  const [erreurs, setErreurs] = useState({});
  const [enCours, setEnCours] = useState(false);

  const force = !!utilisateur?.doit_changer_mdp;

  const soumettre = async (e) => {
    e.preventDefault();
    setErreur('');
    setErreurs({});
    setEnCours(true);
    try {
      await api.post('/api/auth/changer-mot-de-passe', {
        mot_de_passe_actuel: actuel,
        nouveau_mot_de_passe: nouveau,
        nouveau_mot_de_passe_confirmation: confirmation,
      });
      const donnees = await recharger();
      const u = donnees?.utilisateur;
      if (u?.totp_requis && !u?.totp_active) {
        naviguer('/enrolement-2fa');
      } else {
        naviguer('/');
      }
    } catch (err) {
      setErreurs(erreursValidation(err));
      if (err.response?.status !== 422) {
        setErreur(messageErreur(err, 'Le changement de mot de passe a échoué.'));
      }
    } finally {
      setEnCours(false);
    }
  };

  return (
    <div className="page-publique">
      <div className="carte-publique">
        <h1>Changer le mot de passe</h1>
        <p className="sous-titre">
          {force
            ? 'Vous devez changer votre mot de passe avant de continuer.'
            : 'Définissez un nouveau mot de passe.'}
        </p>

        {erreur && <div className="alerte alerte-erreur">{erreur}</div>}
        <div className="alerte alerte-info">
          Politique : 10 caractères minimum, avec au moins une majuscule, une minuscule et un chiffre.
        </div>

        <form onSubmit={soumettre}>
          <div className="champ">
            <label htmlFor="actuel" className="obligatoire">
              Mot de passe actuel
            </label>
            <input
              id="actuel"
              type="password"
              autoFocus
              autoComplete="current-password"
              value={actuel}
              onChange={(e) => setActuel(e.target.value)}
            />
            {erreurs.mot_de_passe_actuel && (
              <div className="champ-erreur">{erreurs.mot_de_passe_actuel.join(' ')}</div>
            )}
          </div>
          <div className="champ">
            <label htmlFor="nouveau" className="obligatoire">
              Nouveau mot de passe
            </label>
            <input
              id="nouveau"
              type="password"
              autoComplete="new-password"
              value={nouveau}
              onChange={(e) => setNouveau(e.target.value)}
            />
            {erreurs.nouveau_mot_de_passe && (
              <div className="champ-erreur">{erreurs.nouveau_mot_de_passe.join(' ')}</div>
            )}
          </div>
          <div className="champ">
            <label htmlFor="confirmation" className="obligatoire">
              Confirmation du nouveau mot de passe
            </label>
            <input
              id="confirmation"
              type="password"
              autoComplete="new-password"
              value={confirmation}
              onChange={(e) => setConfirmation(e.target.value)}
            />
          </div>
          <button type="submit" className="btn" disabled={enCours}>
            {enCours ? 'Enregistrement…' : 'Changer le mot de passe'}
          </button>
        </form>

        <div className="liens-publics">
          <button type="button" className="btn-lien" onClick={deconnecter}>
            Se déconnecter
          </button>
        </div>
      </div>
    </div>
  );
}
