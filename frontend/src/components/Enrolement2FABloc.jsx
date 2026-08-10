import { useState } from 'react';
import api, { erreursValidation, messageErreur } from '../api';

/**
 * Bloc réutilisable d'enrôlement 2FA (TOTP) :
 * 1) démarrage → QR code + secret ; 2) confirmation par code ; 3) affichage unique des codes de secours.
 */
export default function Enrolement2FABloc({ onTermine }) {
  const [enrolement, setEnrolement] = useState(null); // {qr_svg, secret, otpauth_uri}
  const [code, setCode] = useState('');
  const [codesSecours, setCodesSecours] = useState(null);
  const [erreur, setErreur] = useState('');
  const [erreurs, setErreurs] = useState({});
  const [enCours, setEnCours] = useState(false);
  const [copie, setCopie] = useState(false);

  const demarrer = async () => {
    setErreur('');
    setEnCours(true);
    try {
      const reponse = await api.post('/api/auth/2fa/enroler');
      setEnrolement(reponse.data);
    } catch (err) {
      setErreur(messageErreur(err, "Impossible de démarrer l'enrôlement 2FA."));
    } finally {
      setEnCours(false);
    }
  };

  const confirmer = async (e) => {
    e.preventDefault();
    setErreur('');
    setErreurs({});
    setEnCours(true);
    try {
      const reponse = await api.post('/api/auth/2fa/confirmer', { code });
      setCodesSecours(reponse.data?.codes_secours || []);
    } catch (err) {
      setErreurs(erreursValidation(err));
      if (err.response?.status !== 422) {
        setErreur(messageErreur(err, 'Code invalide.'));
      }
    } finally {
      setEnCours(false);
    }
  };

  const copierCodes = async () => {
    try {
      await navigator.clipboard.writeText((codesSecours || []).join('\n'));
      setCopie(true);
      setTimeout(() => setCopie(false), 2000);
    } catch {
      setErreur('La copie automatique a échoué. Copiez les codes manuellement.');
    }
  };

  if (codesSecours) {
    return (
      <div>
        <div className="alerte alerte-succes">
          La double authentification est maintenant activée.
        </div>
        <div className="alerte alerte-avertissement">
          Conservez précieusement ces codes de secours : ils ne seront affichés qu'UNE SEULE FOIS.
          Chaque code ne peut être utilisé qu'une fois si vous perdez l'accès à votre application
          d'authentification.
        </div>
        <div className="codes-secours">
          {codesSecours.map((c) => (
            <code key={c}>{c}</code>
          ))}
        </div>
        <div style={{ display: 'flex', gap: '0.6rem' }}>
          <button type="button" className="btn btn-secondaire" onClick={copierCodes}>
            {copie ? 'Copié !' : 'Copier les codes'}
          </button>
          <button type="button" className="btn" onClick={onTermine}>
            J'ai enregistré mes codes — Continuer
          </button>
        </div>
      </div>
    );
  }

  if (!enrolement) {
    return (
      <div>
        {erreur && <div className="alerte alerte-erreur">{erreur}</div>}
        <p>
          La double authentification (2FA) ajoute une couche de sécurité : à chaque connexion, un code
          généré par une application d'authentification (Google Authenticator, Microsoft
          Authenticator, etc.) vous sera demandé.
        </p>
        <button type="button" className="btn" onClick={demarrer} disabled={enCours}>
          {enCours ? 'Préparation…' : "Démarrer l'enrôlement"}
        </button>
      </div>
    );
  }

  return (
    <div>
      {erreur && <div className="alerte alerte-erreur">{erreur}</div>}
      <p>
        1. Scannez ce QR code avec votre application d'authentification, ou saisissez le secret
        manuellement.
      </p>
      {/* SVG fourni par l'API (source de confiance interne). */}
      <div className="qr-2fa" dangerouslySetInnerHTML={{ __html: enrolement.qr_svg }} />
      <p style={{ textAlign: 'center' }}>
        Secret : <code>{enrolement.secret}</code>
      </p>
      <p>2. Saisissez le code à 6 chiffres affiché par l'application pour confirmer.</p>
      <form onSubmit={confirmer}>
        <div className="champ">
          <label htmlFor="code2fa-confirm" className="obligatoire">
            Code de vérification
          </label>
          <input
            id="code2fa-confirm"
            type="text"
            inputMode="numeric"
            maxLength={6}
            value={code}
            onChange={(e) => setCode(e.target.value)}
          />
          {erreurs.code && <div className="champ-erreur">{erreurs.code.join(' ')}</div>}
        </div>
        <button type="submit" className="btn" disabled={enCours || code.length !== 6}>
          {enCours ? 'Vérification…' : 'Confirmer'}
        </button>
      </form>
    </div>
  );
}
