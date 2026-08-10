import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api, { erreursValidation, messageErreur } from '../api';
import { useAuth } from '../AuthContext';

export default function Connexion() {
  const { appliquer } = useAuth();
  const naviguer = useNavigate();

  const [identifiant, setIdentifiant] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [etape2fa, setEtape2fa] = useState(false);
  const [code2fa, setCode2fa] = useState('');
  const [erreur, setErreur] = useState('');
  const [erreurs, setErreurs] = useState({});
  const [enCours, setEnCours] = useState(false);

  const apresConnexion = (donnees) => {
    appliquer(donnees);
    const utilisateur = donnees?.utilisateur;
    if (utilisateur?.doit_changer_mdp) {
      naviguer('/changer-mot-de-passe');
    } else if (utilisateur?.totp_requis && !utilisateur?.totp_active) {
      naviguer('/enrolement-2fa');
    } else {
      naviguer('/');
    }
  };

  const soumettre = async (e) => {
    e.preventDefault();
    setErreur('');
    setErreurs({});
    setEnCours(true);
    try {
      if (etape2fa) {
        const reponse = await api.post('/api/auth/login/2fa', { code: code2fa });
        apresConnexion(reponse.data);
      } else {
        const reponse = await api.post('/api/auth/login', { identifiant, password: motDePasse });
        if (reponse.data?.etape === '2fa') {
          setEtape2fa(true);
        } else {
          apresConnexion(reponse.data);
        }
      }
    } catch (err) {
      setErreurs(erreursValidation(err));
      setErreur(
        err.response?.status === 422
          ? ''
          : messageErreur(err, 'Connexion impossible. Vérifiez vos identifiants.')
      );
    } finally {
      setEnCours(false);
    }
  };

  return (
    <div className="page-publique">
      <div className="carte-publique">
        <h1>Vignettes carburant</h1>
        <p className="sous-titre">Connectez-vous à votre espace</p>

        {erreur && <div className="alerte alerte-erreur">{erreur}</div>}

        <form onSubmit={soumettre}>
          {etape2fa ? (
            <div className="champ">
              <label htmlFor="code2fa" className="obligatoire">
                Code de vérification
              </label>
              <input
                id="code2fa"
                type="text"
                autoFocus
                autoComplete="one-time-code"
                placeholder="Code TOTP à 6 chiffres ou code de secours XXXX-XXXX"
                value={code2fa}
                onChange={(e) => setCode2fa(e.target.value)}
              />
              <div className="champ-aide">
                Saisissez le code à 6 chiffres de votre application d'authentification, ou un code de
                secours au format XXXX-XXXX.
              </div>
              {erreurs.code && <div className="champ-erreur">{erreurs.code.join(' ')}</div>}
            </div>
          ) : (
            <>
              <div className="champ">
                <label htmlFor="identifiant" className="obligatoire">
                  Identifiant ou e-mail
                </label>
                <input
                  id="identifiant"
                  type="text"
                  autoFocus
                  autoComplete="username"
                  value={identifiant}
                  onChange={(e) => setIdentifiant(e.target.value)}
                />
                {erreurs.identifiant && (
                  <div className="champ-erreur">{erreurs.identifiant.join(' ')}</div>
                )}
              </div>
              <div className="champ">
                <label htmlFor="motDePasse" className="obligatoire">
                  Mot de passe
                </label>
                <input
                  id="motDePasse"
                  type="password"
                  autoComplete="current-password"
                  value={motDePasse}
                  onChange={(e) => setMotDePasse(e.target.value)}
                />
                {erreurs.password && <div className="champ-erreur">{erreurs.password.join(' ')}</div>}
              </div>
            </>
          )}

          <button type="submit" className="btn" disabled={enCours}>
            {enCours ? 'Connexion…' : etape2fa ? 'Valider le code' : 'Se connecter'}
          </button>
        </form>

        <div className="liens-publics">
          <Link to="/mot-de-passe-oublie">Mot de passe oublié ?</Link>
        </div>
      </div>
    </div>
  );
}
