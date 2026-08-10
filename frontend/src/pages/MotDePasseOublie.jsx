import { useState } from 'react';
import { Link } from 'react-router-dom';
import api, { erreursValidation } from '../api';

export default function MotDePasseOublie() {
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState('');
  const [erreurs, setErreurs] = useState({});
  const [enCours, setEnCours] = useState(false);

  const soumettre = async (e) => {
    e.preventDefault();
    setMessage('');
    setErreurs({});
    setEnCours(true);
    try {
      const reponse = await api.post('/api/auth/mot-de-passe-oublie', { email });
      setMessage(
        reponse.data?.message ||
          'Si un compte correspond à cette adresse, un e-mail de réinitialisation a été envoyé.'
      );
    } catch (err) {
      if (err.response?.status === 422) {
        setErreurs(erreursValidation(err));
      } else {
        // Message neutre dans tous les cas.
        setMessage('Si un compte correspond à cette adresse, un e-mail de réinitialisation a été envoyé.');
      }
    } finally {
      setEnCours(false);
    }
  };

  return (
    <div className="page-publique">
      <div className="carte-publique">
        <h1>Mot de passe oublié</h1>
        <p className="sous-titre">
          Saisissez votre adresse e-mail pour recevoir un lien de réinitialisation.
        </p>

        {message && <div className="alerte alerte-info">{message}</div>}

        <form onSubmit={soumettre}>
          <div className="champ">
            <label htmlFor="email" className="obligatoire">
              Adresse e-mail
            </label>
            <input
              id="email"
              type="email"
              autoFocus
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
            {erreurs.email && <div className="champ-erreur">{erreurs.email.join(' ')}</div>}
          </div>
          <button type="submit" className="btn" disabled={enCours}>
            {enCours ? 'Envoi…' : 'Envoyer le lien'}
          </button>
        </form>

        <div className="liens-publics">
          <Link to="/connexion">Retour à la connexion</Link>
        </div>
      </div>
    </div>
  );
}
