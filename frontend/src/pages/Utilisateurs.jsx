import { useEffect, useState } from 'react';
import api, { erreursValidation, messageErreur } from '../api';
import ListePage, { BadgeActif } from '../components/ListePage';
import { Modal, ModalConfirmation } from '../components/Modal';

const SOURCE_ROLES = '/api/roles';
const SOURCE_SERVICES = '/api/services?actif=true&par_page=100';
const SOURCE_SITES = '/api/sites?actif=true&par_page=100';

const CHAMPS_BASE = [
  { nom: 'nom', libelle: 'Nom', requis: true },
  { nom: 'prenom', libelle: 'Prénom', requis: true },
  { nom: 'email', libelle: 'E-mail', type: 'email', requis: true },
  { nom: 'username', libelle: "Nom d'utilisateur", requis: true },
  { nom: 'telephone', libelle: 'Téléphone', type: 'tel' },
  { nom: 'role_id', libelle: 'Rôle', type: 'select', source: SOURCE_ROLES, requis: true },
  { nom: 'service_id', libelle: 'Service', type: 'select', source: SOURCE_SERVICES },
  { nom: 'site_id', libelle: 'Site', type: 'select', source: SOURCE_SITES },
];

function ModalReinitialisationMdp({ utilisateur, onFermer }) {
  const [motDePasse, setMotDePasse] = useState('');
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
      await api.post(`/api/utilisateurs/${utilisateur.id}/reinitialiser-mdp`, {
        nouveau_mot_de_passe: motDePasse,
      });
      setSucces(true);
    } catch (err) {
      setErreurs(erreursValidation(err));
      if (err.response?.status !== 422) {
        setErreur(messageErreur(err, 'La réinitialisation a échoué.'));
      }
    } finally {
      setEnCours(false);
    }
  };

  return (
    <Modal titre={`Réinitialiser le mot de passe — ${utilisateur.nom_complet}`} onFermer={onFermer}>
      {succes ? (
        <>
          <div className="alerte alerte-succes">
            Le mot de passe a été réinitialisé. L'utilisateur devra le changer à sa prochaine
            connexion.
          </div>
          <button type="button" className="btn" onClick={onFermer}>
            Fermer
          </button>
        </>
      ) : (
        <form onSubmit={soumettre}>
          {erreur && <div className="alerte alerte-erreur">{erreur}</div>}
          <div className="champ">
            <label htmlFor="nouveau-mdp" className="obligatoire">
              Nouveau mot de passe
            </label>
            <input
              id="nouveau-mdp"
              type="password"
              autoComplete="new-password"
              value={motDePasse}
              onChange={(e) => setMotDePasse(e.target.value)}
            />
            <div className="champ-aide">
              10 caractères minimum, avec au moins une majuscule, une minuscule et un chiffre.
            </div>
            {erreurs.nouveau_mot_de_passe && (
              <div className="champ-erreur">{erreurs.nouveau_mot_de_passe.join(' ')}</div>
            )}
          </div>
          <button type="submit" className="btn" disabled={enCours}>
            {enCours ? 'Réinitialisation…' : 'Réinitialiser'}
          </button>
        </form>
      )}
    </Modal>
  );
}

function ModalConnexions({ utilisateur, onFermer }) {
  const [connexions, setConnexions] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const reponse = await api.get(`/api/utilisateurs/${utilisateur.id}/connexions`);
        setConnexions(Array.isArray(reponse.data) ? reponse.data : reponse.data?.data || []);
      } catch (err) {
        setErreur(messageErreur(err, "Impossible de charger l'historique de connexion."));
      } finally {
        setChargement(false);
      }
    })();
  }, [utilisateur.id]);

  return (
    <Modal titre={`Dernières connexions — ${utilisateur.nom_complet}`} large onFermer={onFermer}>
      {erreur && <div className="alerte alerte-erreur">{erreur}</div>}
      <div className="conteneur-tableau">
        <table className="tableau">
          <thead>
            <tr>
              <th>Date / heure</th>
              <th>Résultat</th>
              <th>Adresse IP</th>
              <th>Navigateur</th>
            </tr>
          </thead>
          <tbody>
            {chargement ? (
              <tr>
                <td className="tableau-vide" colSpan={4}>
                  Chargement…
                </td>
              </tr>
            ) : connexions.length === 0 ? (
              <tr>
                <td className="tableau-vide" colSpan={4}>
                  Aucune connexion enregistrée.
                </td>
              </tr>
            ) : (
              connexions.map((connexion, index) => (
                <tr key={index}>
                  <td>{connexion.created_at ? new Date(connexion.created_at).toLocaleString('fr-FR') : '—'}</td>
                  <td>
                    <span className={`badge ${connexion.succes ? 'badge-actif' : 'badge-inactif'}`}>
                      {connexion.succes ? 'Succès' : 'Échec'}
                    </span>
                  </td>
                  <td>{connexion.ip_address || '—'}</td>
                  <td style={{ maxWidth: 320, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                    {connexion.user_agent || '—'}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </Modal>
  );
}

const COLONNES = [
  { cle: 'nom_complet', libelle: 'Nom complet', tri: 'nom' },
  { cle: 'username', libelle: "Nom d'utilisateur", tri: true },
  { cle: 'email', libelle: 'E-mail', tri: true },
  { cle: 'role', libelle: 'Rôle', rendu: (l) => l.role?.libelle ?? '—' },
  { cle: 'service', libelle: 'Service', rendu: (l) => l.service?.libelle ?? '—' },
  {
    cle: 'derniere_connexion_at',
    libelle: 'Dernière connexion',
    rendu: (l) =>
      l.derniere_connexion_at ? new Date(l.derniere_connexion_at).toLocaleString('fr-FR') : '—',
  },
  { cle: 'actif', libelle: 'État', rendu: (l) => <BadgeActif actif={l.actif !== false} /> },
];

const FILTRES = [
  { nom: 'role_id', libelle: 'Rôle', source: SOURCE_ROLES },
  { nom: 'service_id', libelle: 'Service', source: SOURCE_SERVICES },
  {
    nom: 'actif',
    libelle: 'État',
    options: [
      { valeur: 'true', libelle: 'Actif' },
      { valeur: 'false', libelle: 'Inactif' },
    ],
  },
];

export default function Utilisateurs() {
  const [modalMdp, setModalMdp] = useState(null);
  const [modalConnexions, setModalConnexions] = useState(null);
  const [deverrouillage, setDeverrouillage] = useState(null); // {ligne, recharger}
  const [erreurAction, setErreurAction] = useState('');

  // Le champ mot de passe initial n'apparaît qu'à la création.
  const champs = [
    ...CHAMPS_BASE,
    {
      nom: 'mot_de_passe_initial',
      libelle: 'Mot de passe initial',
      type: 'password',
      large: true,
      aide: "L'utilisateur devra changer ce mot de passe à sa première connexion.",
    },
  ];

  return (
    <>
      {erreurAction && <div className="alerte alerte-erreur">{erreurAction}</div>}
      <ListePage
        titre="Utilisateurs"
        ressource="utilisateurs"
        domaine="utilisateur"
        colonnes={COLONNES}
        filtres={FILTRES}
        champs={champs}
        libelleCreation="Nouvel utilisateur"
        preparerFormulaire={(l) => ({
          nom: l.nom,
          prenom: l.prenom,
          email: l.email,
          username: l.username,
          telephone: l.telephone,
          role_id: l.role_id ?? l.role?.id ?? null,
          service_id: l.service_id ?? l.service?.id ?? null,
          site_id: l.site_id ?? l.site?.id ?? null,
        })}
        actionsLigne={(ligne, { recharger, peut }) =>
          peut('utilisateur.modifier') && (
            <>
              <button
                type="button"
                className="btn btn-secondaire btn-petit"
                onClick={() => setModalMdp(ligne)}
              >
                Réinit. mdp
              </button>
              <button
                type="button"
                className="btn btn-secondaire btn-petit"
                onClick={() => setDeverrouillage({ ligne, recharger })}
              >
                Déverrouiller
              </button>
              <button
                type="button"
                className="btn btn-secondaire btn-petit"
                onClick={() => setModalConnexions(ligne)}
              >
                Connexions
              </button>
            </>
          )
        }
      />

      {modalMdp && <ModalReinitialisationMdp utilisateur={modalMdp} onFermer={() => setModalMdp(null)} />}
      {modalConnexions && (
        <ModalConnexions utilisateur={modalConnexions} onFermer={() => setModalConnexions(null)} />
      )}
      {deverrouillage && (
        <ModalConfirmation
          titre="Déverrouiller le compte"
          message={`Voulez-vous déverrouiller le compte de « ${deverrouillage.ligne.nom_complet} » ?`}
          libelleConfirmer="Déverrouiller"
          onAnnuler={() => setDeverrouillage(null)}
          onConfirmer={async () => {
            try {
              await api.post(`/api/utilisateurs/${deverrouillage.ligne.id}/deverrouiller`);
              deverrouillage.recharger();
              setDeverrouillage(null);
              setErreurAction('');
            } catch (err) {
              setDeverrouillage(null);
              setErreurAction(messageErreur(err, 'Le déverrouillage a échoué.'));
            }
          }}
        />
      )}
    </>
  );
}
