import { useCallback, useEffect, useState } from 'react';
import api, { erreursValidation, messageErreur } from '../api';
import { useAuth } from '../AuthContext';
import ListePage, { BadgeActif } from '../components/ListePage';
import { Modal, ModalConfirmation } from '../components/Modal';

const CHAMPS = [
  { nom: 'libelle', libelle: 'Libellé', requis: true },
  { nom: 'code', libelle: 'Code', requis: true },
];

function GestionCoupures({ typeVignette, onFermer }) {
  const { peut } = useAuth();
  const [coupures, setCoupures] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [valeur, setValeur] = useState('');
  const [erreur, setErreur] = useState('');
  const [erreurs, setErreurs] = useState({});
  const [enCours, setEnCours] = useState(false);
  const [confirmation, setConfirmation] = useState(null);

  const peutModifier = peut('type_vignette.modifier');
  const peutDesactiver = peut('type_vignette.desactiver');

  const charger = useCallback(async () => {
    setChargement(true);
    try {
      const reponse = await api.get('/api/coupures', {
        params: { type_vignette_id: typeVignette.id, par_page: 100 },
      });
      setCoupures(reponse.data?.data || []);
    } catch (err) {
      setErreur(messageErreur(err, 'Impossible de charger les coupures.'));
    } finally {
      setChargement(false);
    }
  }, [typeVignette.id]);

  useEffect(() => {
    charger();
  }, [charger]);

  const ajouter = async (e) => {
    e.preventDefault();
    setErreur('');
    setErreurs({});
    setEnCours(true);
    try {
      await api.post('/api/coupures', { type_vignette_id: typeVignette.id, valeur });
      setValeur('');
      charger();
    } catch (err) {
      setErreurs(erreursValidation(err));
      if (err.response?.status !== 422) {
        setErreur(messageErreur(err, "L'ajout de la coupure a échoué."));
      }
    } finally {
      setEnCours(false);
    }
  };

  const demanderDesactivation = (coupure) => {
    setConfirmation({
      titre: 'Désactiver la coupure',
      message: `Voulez-vous vraiment désactiver la coupure « ${coupure.valeur} » ?`,
      action: async () => {
        try {
          await api.post(`/api/coupures/${coupure.id}/desactiver`);
          setConfirmation(null);
          charger();
        } catch (err) {
          if (err.response?.status === 409 && err.response.data?.suppression_possible) {
            setConfirmation({
              titre: 'Suppression possible',
              message:
                (err.response.data.message || 'Cette coupure ne peut pas être désactivée.') +
                ' Voulez-vous la supprimer définitivement ?',
              action: async () => {
                await api.delete(`/api/coupures/${coupure.id}`);
                setConfirmation(null);
                charger();
              },
            });
          } else {
            setConfirmation(null);
            setErreur(messageErreur(err, 'La désactivation a échoué.'));
          }
        }
      },
    });
  };

  const reactiver = (coupure) => {
    setConfirmation({
      titre: 'Réactiver la coupure',
      message: `Voulez-vous réactiver la coupure « ${coupure.valeur} » ?`,
      action: async () => {
        await api.post(`/api/coupures/${coupure.id}/reactiver`);
        setConfirmation(null);
        charger();
      },
    });
  };

  return (
    <>
      <Modal titre={`Coupures — ${typeVignette.libelle}`} onFermer={onFermer}>
        {erreur && <div className="alerte alerte-erreur">{erreur}</div>}

        {peutModifier && (
          <form onSubmit={ajouter} className="barre-outils">
            <div className="champ" style={{ flex: 1 }}>
              <label htmlFor="valeur-coupure" className="obligatoire">
                Valeur de la coupure (DH)
              </label>
              <input
                id="valeur-coupure"
                type="number"
                step="any"
                value={valeur}
                onChange={(e) => setValeur(e.target.value)}
              />
              {erreurs.valeur && <div className="champ-erreur">{erreurs.valeur.join(' ')}</div>}
            </div>
            <button type="submit" className="btn" disabled={enCours || valeur === ''}>
              Ajouter
            </button>
          </form>
        )}

        <div className="conteneur-tableau">
          <table className="tableau">
            <thead>
              <tr>
                <th>Valeur</th>
                <th>État</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {chargement ? (
                <tr>
                  <td className="tableau-vide" colSpan={3}>
                    Chargement…
                  </td>
                </tr>
              ) : coupures.length === 0 ? (
                <tr>
                  <td className="tableau-vide" colSpan={3}>
                    Aucune coupure pour ce type de vignette.
                  </td>
                </tr>
              ) : (
                coupures.map((coupure) => (
                  <tr key={coupure.id}>
                    <td>{coupure.valeur}</td>
                    <td>
                      <BadgeActif actif={coupure.actif !== false} />
                    </td>
                    <td className="cellule-actions">
                      {peutDesactiver &&
                        (coupure.actif === false ? (
                          <button
                            type="button"
                            className="btn btn-secondaire btn-petit"
                            onClick={() => reactiver(coupure)}
                          >
                            Réactiver
                          </button>
                        ) : (
                          <button
                            type="button"
                            className="btn btn-danger btn-petit"
                            onClick={() => demanderDesactivation(coupure)}
                          >
                            Désactiver
                          </button>
                        ))}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </Modal>

      {confirmation && (
        <ModalConfirmation
          titre={confirmation.titre}
          message={confirmation.message}
          danger
          onAnnuler={() => setConfirmation(null)}
          onConfirmer={async () => {
            try {
              await confirmation.action();
            } catch (err) {
              setConfirmation(null);
              setErreur(messageErreur(err, "L'action a échoué."));
            }
          }}
        />
      )}
    </>
  );
}

const COLONNES = [
  { cle: 'libelle', libelle: 'Libellé', tri: true },
  { cle: 'code', libelle: 'Code', tri: true },
  {
    cle: 'coupures',
    libelle: 'Coupures',
    rendu: (l) =>
      (l.coupures || [])
        .filter((c) => c.actif !== false)
        .map((c) => c.valeur)
        .join(', ') || '—',
  },
  { cle: 'actif', libelle: 'État', rendu: (l) => <BadgeActif actif={l.actif !== false} /> },
];

const FILTRES = [
  {
    nom: 'actif',
    libelle: 'État',
    options: [
      { valeur: 'true', libelle: 'Actif' },
      { valeur: 'false', libelle: 'Inactif' },
    ],
  },
];

export default function TypesVignette() {
  const [typeSelectionne, setTypeSelectionne] = useState(null);

  return (
    <>
      <ListePage
        titre="Types de vignette"
        ressource="types-vignette"
        domaine="type_vignette"
        colonnes={COLONNES}
        filtres={FILTRES}
        champs={CHAMPS}
        libelleCreation="Nouveau type"
        actionsLigne={(ligne) => (
          <button
            type="button"
            className="btn btn-secondaire btn-petit"
            onClick={() => setTypeSelectionne(ligne)}
          >
            Coupures
          </button>
        )}
      />
      {typeSelectionne && (
        <GestionCoupures typeVignette={typeSelectionne} onFermer={() => setTypeSelectionne(null)} />
      )}
    </>
  );
}
