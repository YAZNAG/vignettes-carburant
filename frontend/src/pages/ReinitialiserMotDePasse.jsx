import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import api, { erreursValidation, messageErreur } from '../api';

export default function ReinitialiserMotDePasse() {
  const [parametres] = useSearchParams();
  const naviguer = useNavigate();
  const token = parametres.get('token') || '';
  const email = parametres.get('email') || '';

  const [motDePasse, setMotDePasse] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [erreur, setErreur] = useState('');
  const [erreurs, setErreurs] = useState({});
  const [succes, setSucces] = useState(false);
  const [enCours, setEnCours] = useState(false);

  const soumettre = async (e) => {
    e.preventDefault();
    setErreur('');
    setErreurs({});
    setEnCours(true);
    try {
      await api.post('/api/auth/reinitialiser-mot-de-passe', {
        token,
        email,
        password: motDePasse,
        password_confirmation: confirmation,
      });
      setSucces(true);
      setTimeout(() => naviguer('/connexion'), 2500);
    } catch (err) {
      setErreurs(erreursValidation(err));
      if (err.response?.status !== 422) {
        setErreur(messageErreur(err, 'La réinitialisation a échoué. Le lien est peut-être expiré.'));
      }
    } finally {
      setEnCours(false);
    }
  };

  return (
    <div className="page-publique">
      <div className="carte-publique">
        <h1>Réinitialiser le mot de passe</h1>
        <p className="sous-titre">{email ? `Compte : ${email}` : 'Définissez un nouveau mot de passe.'}</p>

        {succes ? (
          <div className="alerte alerte-succes">
            Votre mot de passe a été réinitialisé. Redirection vers la page de connexion…
          </div>
        ) : (
          <>
            {erreur && <div className="alerte alerte-erreur">{erreur}</div>}
            {(!token || !email) && (
              <div className="alerte alerte-avertissement">
                Le lien de réinitialisation est incomplet. Veuillez utiliser le lien reçu par e-mail.
              </div>
            )}

            <form onSubmit={soumettre}>
              <div className="champ">
                <label htmlFor="motDePasse" className="obligatoire">
                  Nouveau mot de passe
                </label>
                <input
                  id="motDePasse"
                  type="password"
                  autoComplete="new-password"
                  value={motDePasse}
                  onChange={(e) => setMotDePasse(e.target.value)}
                />
                <div className="champ-aide">
                  10 caractères minimum, avec au moins une majuscule, une minuscule et un chiffre.
                </div>
                {erreurs.password && <div className="champ-erreur">{erreurs.password.join(' ')}</div>}
              </div>
              <div className="champ">
                <label htmlFor="confirmation" className="obligatoire">
                  Confirmation du mot de passe
                </label>
                <input
                  id="confirmation"
                  type="password"
                  autoComplete="new-password"
                  value={confirmation}
                  onChange={(e) => setConfirmation(e.target.value)}
                />
              </div>
              <button type="submit" className="btn" disabled={enCours || !token || !email}>
                {enCours ? 'Réinitialisation…' : 'Réinitialiser'}
              </button>
            </form>
          </>
        )}

        <div className="liens-publics">
          <Link to="/connexion">Retour à la connexion</Link>
        </div>
      </div>
    </div>
  );
}
