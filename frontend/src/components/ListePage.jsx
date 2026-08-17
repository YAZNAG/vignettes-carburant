import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import api, { erreursValidation, messageErreur, telechargerBlob } from '../api';
import { useAuth } from '../AuthContext';
import { FormulaireChamps, useOptions } from './Formulaire';
import { Modal, ModalConfirmation } from './Modal';

export function BadgeActif({ actif }) {
  return <span className={`badge ${actif ? 'badge-actif' : 'badge-inactif'}`}>{actif ? 'Actif' : 'Inactif'}</span>;
}

function FiltreSelect({ filtre, valeur, onChange }) {
  const optionsDistantes = useOptions(filtre.source, filtre.mapper);
  const options = filtre.source ? optionsDistantes : filtre.options || [];
  return (
    <div className="champ">
      <label>{filtre.libelle}</label>
      <select value={valeur ?? ''} onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}>
        <option value="">Tous</option>
        {options.map((o) => (
          <option key={o.valeur} value={o.valeur}>
            {o.libelle}
          </option>
        ))}
      </select>
    </div>
  );
}

const FILTRES_FIXES_VIDE = {};

/**
 * Page de liste générique : recherche (debounce 400 ms), filtres, tri, pagination,
 * export Excel, création/modification, désactivation/réactivation/suppression,
 * gestion du 409 de similarité (confirmer_similaire).
 * NB : `filtresFixes` doit être une référence stable (objet défini hors composant).
 */
export default function ListePage({
  titre,
  ressource,
  domaine,
  colonnes,
  filtres = [],
  champs = null,
  valeursInitiales = {},
  preparerFormulaire = null,
  transformerEnvoi = null,
  similarite = false,
  sansDesactivation = false,
  sansExport = false,
  libelleCreation = 'Nouveau',
  actionsLigne = null,
  filtresFixes = FILTRES_FIXES_VIDE,
}) {
  const { peut } = useAuth();

  const [donnees, setDonnees] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreurGlobale, setErreurGlobale] = useState('');
  const [page, setPage] = useState(1);
  const [dernierePage, setDernierePage] = useState(1);
  const [total, setTotal] = useState(0);
  const [parPage, setParPage] = useState(15);
  const [recherche, setRecherche] = useState('');
  const [rechercheDebounce, setRechercheDebounce] = useState('');
  const [tri, setTri] = useState(null);
  const [sens, setSens] = useState('asc');
  const [valeursFiltres, setValeursFiltres] = useState({});

  // Formulaire création / modification
  const [formulaireVisible, setFormulaireVisible] = useState(false);
  const [ligneEnEdition, setLigneEnEdition] = useState(null);
  const [valeursFormulaire, setValeursFormulaire] = useState({});
  const [erreursFormulaire, setErreursFormulaire] = useState({});
  const [erreurFormulaire, setErreurFormulaire] = useState('');
  const [envoiEnCours, setEnvoiEnCours] = useState(false);
  const [dialogueSimilaire, setDialogueSimilaire] = useState(null); // {message, similaires}

  // Confirmations d'actions
  const [confirmation, setConfirmation] = useState(null); // {titre, message, danger, action}
  const [actionEnCours, setActionEnCours] = useState(false);

  const minuterieRecherche = useRef(null);

  const parametres = useMemo(() => {
    const p = { page, par_page: parPage, ...filtresFixes };
    if (rechercheDebounce) p.recherche = rechercheDebounce;
    if (tri) {
      p.tri = tri;
      p.sens = sens;
    }
    for (const [cle, valeur] of Object.entries(valeursFiltres)) {
      if (valeur !== null && valeur !== undefined && valeur !== '') p[cle] = valeur;
    }
    return p;
  }, [page, parPage, rechercheDebounce, tri, sens, valeursFiltres, filtresFixes]);

  const charger = useCallback(async () => {
    setChargement(true);
    setErreurGlobale('');
    try {
      const reponse = await api.get(`/api/${ressource}`, { params: parametres });
      const corps = reponse.data;
      setDonnees(corps.data || []);
      setDernierePage(corps.last_page || 1);
      setTotal(corps.total ?? (corps.data || []).length);
    } catch (erreur) {
      setErreurGlobale(messageErreur(erreur, 'Impossible de charger les données.'));
    } finally {
      setChargement(false);
    }
  }, [ressource, parametres]);

  useEffect(() => {
    charger();
  }, [charger]);

  // Recherche avec debounce 400 ms.
  const surRecherche = (valeur) => {
    setRecherche(valeur);
    clearTimeout(minuterieRecherche.current);
    minuterieRecherche.current = setTimeout(() => {
      setPage(1);
      setRechercheDebounce(valeur);
    }, 400);
  };

  const surTri = (colonne) => {
    if (!colonne.tri) return;
    const cle = typeof colonne.tri === 'string' ? colonne.tri : colonne.cle;
    if (tri === cle) {
      setSens((s) => (s === 'asc' ? 'desc' : 'asc'));
    } else {
      setTri(cle);
      setSens('asc');
    }
  };

  const exporter = async () => {
    try {
      const { page: _page, par_page: _parPage, ...filtresExport } = parametres;
      await telechargerBlob(`/api/${ressource}-export`, filtresExport, `${ressource}.xlsx`);
    } catch (erreur) {
      setErreurGlobale(messageErreur(erreur, "L'export a échoué."));
    }
  };

  // ----- Formulaire -----
  const ouvrirCreation = () => {
    setLigneEnEdition(null);
    setValeursFormulaire({ ...valeursInitiales });
    setErreursFormulaire({});
    setErreurFormulaire('');
    setFormulaireVisible(true);
  };

  const ouvrirModification = (ligne) => {
    setLigneEnEdition(ligne);
    if (preparerFormulaire) {
      setValeursFormulaire(preparerFormulaire(ligne));
    } else {
      const valeurs = {};
      for (const champ of champs || []) {
        valeurs[champ.nom] = ligne[champ.nom] ?? null;
      }
      setValeursFormulaire(valeurs);
    }
    setErreursFormulaire({});
    setErreurFormulaire('');
    setFormulaireVisible(true);
  };

  const envoyerFormulaire = async (confirmerSimilaire = false) => {
    setEnvoiEnCours(true);
    setErreursFormulaire({});
    setErreurFormulaire('');
    const corps = transformerEnvoi
      ? transformerEnvoi({ ...valeursFormulaire })
      : { ...valeursFormulaire };
    if (confirmerSimilaire) corps.confirmer_similaire = true;
    try {
      if (ligneEnEdition) {
        await api.put(`/api/${ressource}/${ligneEnEdition.id}`, corps);
      } else {
        await api.post(`/api/${ressource}`, corps);
      }
      setFormulaireVisible(false);
      setDialogueSimilaire(null);
      charger();
    } catch (erreur) {
      const statut = erreur.response?.status;
      if (statut === 422) {
        setErreursFormulaire(erreursValidation(erreur));
        setDialogueSimilaire(null);
      } else if (statut === 409 && similarite && erreur.response.data?.confirmation_requise) {
        setDialogueSimilaire({
          message: erreur.response.data.message,
          similaires: erreur.response.data.similaires || [],
        });
      } else {
        setErreurFormulaire(messageErreur(erreur, "L'enregistrement a échoué."));
        setDialogueSimilaire(null);
      }
    } finally {
      setEnvoiEnCours(false);
    }
  };

  // ----- Désactivation / réactivation / suppression -----
  const demanderDesactivation = (ligne) => {
    setConfirmation({
      titre: 'Désactiver',
      message: `Voulez-vous vraiment désactiver « ${libelleLigne(ligne)} » ?`,
      danger: true,
      action: async () => {
        try {
          await api.post(`/api/${ressource}/${ligne.id}/desactiver`);
          setConfirmation(null);
          charger();
        } catch (erreur) {
          if (erreur.response?.status === 409 && erreur.response.data?.suppression_possible) {
            setConfirmation({
              titre: 'Suppression possible',
              message:
                (erreur.response.data.message || 'Cet élément ne peut pas être désactivé.') +
                ' Voulez-vous le supprimer définitivement ?',
              danger: true,
              action: async () => {
                await api.delete(`/api/${ressource}/${ligne.id}`);
                setConfirmation(null);
                charger();
              },
            });
          } else {
            setConfirmation(null);
            setErreurGlobale(messageErreur(erreur, 'La désactivation a échoué.'));
          }
        }
      },
    });
  };

  const demanderReactivation = (ligne) => {
    setConfirmation({
      titre: 'Réactiver',
      message: `Voulez-vous réactiver « ${libelleLigne(ligne)} » ?`,
      danger: false,
      action: async () => {
        await api.post(`/api/${ressource}/${ligne.id}/reactiver`);
        setConfirmation(null);
        charger();
      },
    });
  };

  const libelleLigne = (ligne) =>
    ligne.nom_complet ||
    ligne.libelle ||
    ligne.raison_sociale ||
    ligne.immatriculation ||
    ligne.username ||
    ligne.annee ||
    `n° ${ligne.id}`;

  const executerConfirmation = async () => {
    if (!confirmation) return;
    setActionEnCours(true);
    try {
      await confirmation.action();
    } catch (erreur) {
      setConfirmation(null);
      setErreurGlobale(messageErreur(erreur, "L'action a échoué."));
    } finally {
      setActionEnCours(false);
    }
  };

  const peutModifier = domaine && peut(`${domaine}.modifier`);
  const peutDesactiver = domaine && !sansDesactivation && peut(`${domaine}.desactiver`);
  const peutCreer = champs && peutModifier;

  return (
    <div>
      <h1>{titre}</h1>

      {erreurGlobale && <div className="alerte alerte-erreur">{erreurGlobale}</div>}

      <div className="carte">
        <div className="barre-outils">
          <div className="champ" style={{ minWidth: 220 }}>
            <label>Recherche</label>
            <input
              type="text"
              placeholder="Rechercher…"
              value={recherche}
              onChange={(e) => surRecherche(e.target.value)}
            />
          </div>
          {filtres.map((filtre) => (
            <FiltreSelect
              key={filtre.nom}
              filtre={filtre}
              valeur={valeursFiltres[filtre.nom]}
              onChange={(valeur) => {
                setPage(1);
                setValeursFiltres((v) => ({ ...v, [filtre.nom]: valeur }));
              }}
            />
          ))}
          <div className="espace" />
          {!sansExport && (
            <button type="button" className="btn btn-secondaire" onClick={exporter}>
              Exporter Excel
            </button>
          )}
          {peutCreer && (
            <button type="button" className="btn" onClick={ouvrirCreation}>
              + {libelleCreation}
            </button>
          )}
        </div>

        <div className="conteneur-tableau">
          <table className="tableau">
            <thead>
              <tr>
                {colonnes.map((colonne) => {
                  const cleTri = typeof colonne.tri === 'string' ? colonne.tri : colonne.cle;
                  return (
                    <th
                      key={colonne.cle}
                      className={colonne.tri ? 'triable' : ''}
                      onClick={() => surTri(colonne)}
                    >
                      {colonne.libelle}
                      {colonne.tri && tri === cleTri && (sens === 'asc' ? ' ▲' : ' ▼')}
                    </th>
                  );
                })}
                <th></th>
              </tr>
            </thead>
            <tbody>
              {chargement ? (
                <tr>
                  <td className="tableau-vide" colSpan={colonnes.length + 1}>
                    Chargement…
                  </td>
                </tr>
              ) : donnees.length === 0 ? (
                <tr>
                  <td className="tableau-vide" colSpan={colonnes.length + 1}>
                    Aucun résultat.
                  </td>
                </tr>
              ) : (
                donnees.map((ligne) => (
                  <tr key={ligne.id}>
                    {colonnes.map((colonne) => (
                      <td key={colonne.cle}>
                        {colonne.rendu ? colonne.rendu(ligne) : ligne[colonne.cle] ?? '—'}
                      </td>
                    ))}
                    <td className="cellule-actions">
                      {actionsLigne && actionsLigne(ligne, { recharger: charger, peut })}
                      {peutModifier && champs && (
                        <button
                          type="button"
                          className="btn btn-secondaire btn-petit"
                          onClick={() => ouvrirModification(ligne)}
                        >
                          Modifier
                        </button>
                      )}
                      {peutDesactiver &&
                        (ligne.actif === false ? (
                          <button
                            type="button"
                            className="btn btn-secondaire btn-petit"
                            onClick={() => demanderReactivation(ligne)}
                          >
                            Réactiver
                          </button>
                        ) : (
                          <button
                            type="button"
                            className="btn btn-danger btn-petit"
                            onClick={() => demanderDesactivation(ligne)}
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

        <div className="pagination">
          <span className="info">
            {total} résultat{total > 1 ? 's' : ''} — page {page} / {dernierePage}
          </span>
          <div className="espace" />
          <select
            value={parPage}
            onChange={(e) => {
              setPage(1);
              setParPage(Number(e.target.value));
            }}
          >
            {[10, 15, 25, 50, 100].map((n) => (
              <option key={n} value={n}>
                {n} / page
              </option>
            ))}
          </select>
          <button
            type="button"
            className="btn btn-secondaire btn-petit"
            disabled={page <= 1}
            onClick={() => setPage((p) => p - 1)}
          >
            ‹ Précédent
          </button>
          <button
            type="button"
            className="btn btn-secondaire btn-petit"
            disabled={page >= dernierePage}
            onClick={() => setPage((p) => p + 1)}
          >
            Suivant ›
          </button>
        </div>
      </div>

      {formulaireVisible && champs && (
        <Modal
          titre={ligneEnEdition ? `Modifier ${libelleLigne(ligneEnEdition)}` : libelleCreation}
          large
          onFermer={() => setFormulaireVisible(false)}
          pied={
            <>
              <button
                type="button"
                className="btn btn-secondaire"
                onClick={() => setFormulaireVisible(false)}
                disabled={envoiEnCours}
              >
                Annuler
              </button>
              <button
                type="button"
                className="btn"
                onClick={() => envoyerFormulaire(false)}
                disabled={envoiEnCours}
              >
                {envoiEnCours ? 'Enregistrement…' : 'Enregistrer'}
              </button>
            </>
          }
        >
          {erreurFormulaire && <div className="alerte alerte-erreur">{erreurFormulaire}</div>}
          <FormulaireChamps
            champs={champs}
            valeurs={valeursFormulaire}
            setValeurs={setValeursFormulaire}
            erreurs={erreursFormulaire}
          />
        </Modal>
      )}

      {dialogueSimilaire && (
        <ModalConfirmation
          titre="Avertissement : élément similaire"
          message={dialogueSimilaire.message}
          libelleConfirmer={ligneEnEdition ? 'Confirmer la modification' : 'Confirmer la création'}
          enCours={envoiEnCours}
          onAnnuler={() => setDialogueSimilaire(null)}
          onConfirmer={() => envoyerFormulaire(true)}
        >
          {dialogueSimilaire.similaires.length > 0 && (
            <ul>
              {dialogueSimilaire.similaires.map((s) => (
                <li key={s.id}>{s.valeur}</li>
              ))}
            </ul>
          )}
        </ModalConfirmation>
      )}

      {confirmation && (
        <ModalConfirmation
          titre={confirmation.titre}
          message={confirmation.message}
          danger={confirmation.danger}
          enCours={actionEnCours}
          onAnnuler={() => setConfirmation(null)}
          onConfirmer={executerConfirmation}
        />
      )}
    </div>
  );
}
