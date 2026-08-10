import { useState } from 'react';
import api, { erreursValidation, messageErreur } from '../api';
import { useAuth } from '../AuthContext';
import Enrolement2FABloc from '../components/Enrolement2FABloc';
import { Modal } from '../components/Modal';

function ChangementMotDePasse() {
  const [actuel, setActuel] = useState('');
  const [nouveau, setNouveau] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [erreur, setErreur] = useState('');
  const [erreurs, setErreurs] = useState({});
  const [succes, setSucces] = useState('');
  const [enCours, setEnCours] = useState(false);

  const soumettre = async (e) => {
    e.preventDefault();
    setErreur('');
    setErreurs({});
    setSucces('');
    setEnCours(true);
    try {
      await api.post('/api/auth/changer-mot-de-passe', {
        mot_de_passe_actuel: actuel,
        nouveau_mot_de_passe: nouveau,
        nouveau_mot_de_passe_confirmation: confirmation,
      });
      setSucces('Votre mot de passe a été changé.');
      setActuel('');
      setNouveau('');
      setConfirmation('');
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
    <div className="carte">
      <h2>Changer mon mot de passe</h2>
      {erreur && <div className="alerte alerte-erreur">{erreur}</div>}
      {succes && <div className="alerte alerte-succes">{succes}</div>}
      <div className="alerte alerte-info">
        Politique : 10 caractères minimum, avec au moins une majuscule, une minuscule et un chiffre.
      </div>
      <form onSubmit={soumettre} style={{ maxWidth: 420 }}>
        <div className="champ">
          <label htmlFor="mdp-actuel" className="obligatoire">
            Mot de passe actuel
          </label>
          <input
            id="mdp-actuel"
            type="password"
            autoComplete="current-password"
            value={actuel}
            onChange={(e) => setActuel(e.target.value)}
          />
          {erreurs.mot_de_passe_actuel && (
            <div className="champ-erreur">{erreurs.mot_de_passe_actuel.join(' ')}</div>
          )}
        </div>
        <div className="champ">
          <label htmlFor="mdp-nouveau" className="obligatoire">
            Nouveau mot de passe
          </label>
          <input
            id="mdp-nouveau"
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
          <label htmlFor="mdp-confirmation" className="obligatoire">
            Confirmation du nouveau mot de passe
          </label>
          <input
            id="mdp-confirmation"
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
    </div>
  );
}

function Gestion2FA() {
  const { utilisateur, recharger } = useAuth();
  const [modalEnrolement, setModalEnrolement] = useState(false);
  const [modalDesactivation, setModalDesactivation] = useState(false);
  const [modalRegeneration, setModalRegeneration] = useState(false);
  const [motDePasse, setMotDePasse] = useState('');
  const [code, setCode] = useState('');
  const [nouveauxCodes, setNouveauxCodes] = useState(null);
  const [erreur, setErreur] = useState('');
  const [erreurs, setErreurs] = useState({});
  const [enCours, setEnCours] = useState(false);
  const [copie, setCopie] = useState(false);

  const totpActive = !!utilisateur?.totp_active;
  const totpImposee = !!utilisateur?.totp_requis;

  const fermerModales = () => {
    setModalDesactivation(false);
    setModalRegeneration(false);
    setMotDePasse('');
    setCode('');
    setErreur('');
    setErreurs({});
  };

  const desactiver = async (e) => {
    e.preventDefault();
    setErreur('');
    setErreurs({});
    setEnCours(true);
    try {
      await api.post('/api/auth/2fa/desactiver', { password: motDePasse, code });
      fermerModales();
      await recharger();
    } catch (err) {
      setErreurs(erreursValidation(err));
      if (err.response?.status !== 422) {
        setErreur(messageErreur(err, 'La désactivation a échoué.'));
      }
    } finally {
      setEnCours(false);
    }
  };

  const regenererCodes = async () => {
    setErreur('');
    setEnCours(true);
    try {
      const reponse = await api.post('/api/auth/2fa/codes-secours');
      setNouveauxCodes(reponse.data?.codes_secours || []);
    } catch (err) {
      setErreur(messageErreur(err, 'La régénération des codes a échoué.'));
    } finally {
      setEnCours(false);
    }
  };

  const copierCodes = async () => {
    try {
      await navigator.clipboard.writeText((nouveauxCodes || []).join('\n'));
      setCopie(true);
      setTimeout(() => setCopie(false), 2000);
    } catch {
      setErreur('La copie automatique a échoué. Copiez les codes manuellement.');
    }
  };

  return (
    <div className="carte">
      <h2>Double authentification (2FA)</h2>
      <p>
        Statut :{' '}
        <span className={`badge ${totpActive ? 'badge-actif' : 'badge-neutre'}`}>
          {totpActive ? 'Activée' : 'Désactivée'}
        </span>
        {totpImposee && (
          <>
            {' '}
            <span className="badge badge-orange">Obligatoire pour votre rôle</span>
          </>
        )}
      </p>

      {!totpActive ? (
        <button type="button" className="btn" onClick={() => setModalEnrolement(true)}>
          Activer la 2FA
        </button>
      ) : (
        <div style={{ display: 'flex', gap: '0.6rem', flexWrap: 'wrap' }}>
          <button type="button" className="btn btn-secondaire" onClick={() => setModalRegeneration(true)}>
            Régénérer les codes de secours
          </button>
          {!totpImposee && (
            <button type="button" className="btn btn-danger" onClick={() => setModalDesactivation(true)}>
              Désactiver la 2FA
            </button>
          )}
        </div>
      )}

      {modalEnrolement && (
        <Modal titre="Activer la double authentification" onFermer={() => setModalEnrolement(false)}>
          <Enrolement2FABloc
            onTermine={async () => {
              setModalEnrolement(false);
              await recharger();
            }}
          />
        </Modal>
      )}

      {modalDesactivation && (
        <Modal titre="Désactiver la double authentification" onFermer={fermerModales}>
          {erreur && <div className="alerte alerte-erreur">{erreur}</div>}
          <form onSubmit={desactiver}>
            <div className="champ">
              <label htmlFor="desac-mdp" className="obligatoire">
                Mot de passe
              </label>
              <input
                id="desac-mdp"
                type="password"
                autoComplete="current-password"
                value={motDePasse}
                onChange={(e) => setMotDePasse(e.target.value)}
              />
              {erreurs.password && <div className="champ-erreur">{erreurs.password.join(' ')}</div>}
            </div>
            <div className="champ">
              <label htmlFor="desac-code" className="obligatoire">
                Code TOTP
              </label>
              <input
                id="desac-code"
                type="text"
                inputMode="numeric"
                maxLength={6}
                value={code}
                onChange={(e) => setCode(e.target.value)}
              />
              {erreurs.code && <div className="champ-erreur">{erreurs.code.join(' ')}</div>}
            </div>
            <button type="submit" className="btn btn-danger" disabled={enCours}>
              {enCours ? 'Désactivation…' : 'Désactiver la 2FA'}
            </button>
          </form>
        </Modal>
      )}

      {modalRegeneration && (
        <Modal
          titre="Régénérer les codes de secours"
          onFermer={() => {
            fermerModales();
            setNouveauxCodes(null);
          }}
        >
          {erreur && <div className="alerte alerte-erreur">{erreur}</div>}
          {nouveauxCodes ? (
            <>
              <div className="alerte alerte-avertissement">
                Conservez ces nouveaux codes : ils ne seront affichés qu'UNE SEULE FOIS. Les anciens
                codes ne sont plus valables.
              </div>
              <div className="codes-secours">
                {nouveauxCodes.map((c) => (
                  <code key={c}>{c}</code>
                ))}
              </div>
              <button type="button" className="btn btn-secondaire" onClick={copierCodes}>
                {copie ? 'Copié !' : 'Copier les codes'}
              </button>
            </>
          ) : (
            <>
              <p>
                La régénération invalide immédiatement vos anciens codes de secours et en génère 8
                nouveaux.
              </p>
              <button type="button" className="btn" onClick={regenererCodes} disabled={enCours}>
                {enCours ? 'Génération…' : 'Générer de nouveaux codes'}
              </button>
            </>
          )}
        </Modal>
      )}
    </div>
  );
}

export default function MonCompte() {
  const { utilisateur } = useAuth();

  return (
    <div>
      <h1>Mon compte</h1>

      <div className="carte">
        <h2>Mes informations</h2>
        <table className="tableau-detail" style={{ maxWidth: 560 }}>
          <tbody>
            <tr>
              <th>Nom complet</th>
              <td>{utilisateur?.nom_complet}</td>
            </tr>
            <tr>
              <th>Nom d'utilisateur</th>
              <td>{utilisateur?.username}</td>
            </tr>
            <tr>
              <th>E-mail</th>
              <td>{utilisateur?.email}</td>
            </tr>
            <tr>
              <th>Rôle</th>
              <td>{utilisateur?.role?.libelle}</td>
            </tr>
            <tr>
              <th>Service</th>
              <td>{utilisateur?.service?.libelle || '—'}</td>
            </tr>
            <tr>
              <th>Site</th>
              <td>{utilisateur?.site?.libelle || '—'}</td>
            </tr>
            <tr>
              <th>Dernière connexion</th>
              <td>
                {utilisateur?.derniere_connexion_at
                  ? new Date(utilisateur.derniere_connexion_at).toLocaleString('fr-FR')
                  : '—'}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <ChangementMotDePasse />
      <Gestion2FA />
    </div>
  );
}
