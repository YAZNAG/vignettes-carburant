import { useRef, useState } from 'react';
import api, { messageErreur, telechargerBlob } from '../api';

const TYPES_IMPORT = [
  { valeur: 'vehicules', libelle: 'Véhicules' },
  { valeur: 'beneficiaires', libelle: 'Bénéficiaires' },
];

const TAILLE_MAX = 5 * 1024 * 1024; // 5 Mo

export default function ImportPage() {
  const [typeImport, setTypeImport] = useState('vehicules');
  const [fichier, setFichier] = useState(null);
  const [previsualisation, setPrevisualisation] = useState(null);
  const [resultat, setResultat] = useState(null);
  const [erreur, setErreur] = useState('');
  const [enCours, setEnCours] = useState(false);
  const champFichierRef = useRef(null);

  const reinitialiser = () => {
    setFichier(null);
    setPrevisualisation(null);
    setResultat(null);
    setErreur('');
    if (champFichierRef.current) champFichierRef.current.value = '';
  };

  const changerType = (valeur) => {
    setTypeImport(valeur);
    reinitialiser();
  };

  const telechargerModele = async () => {
    setErreur('');
    try {
      await telechargerBlob(`/api/import/${typeImport}/modele`, {}, `modele-${typeImport}.xlsx`);
    } catch (err) {
      setErreur(messageErreur(err, 'Le téléchargement du modèle a échoué.'));
    }
  };

  const choisirFichier = (e) => {
    const choisi = e.target.files?.[0] || null;
    setPrevisualisation(null);
    setResultat(null);
    setErreur('');
    if (choisi && choisi.size > TAILLE_MAX) {
      setErreur('Le fichier dépasse la taille maximale de 5 Mo.');
      setFichier(null);
      e.target.value = '';
      return;
    }
    setFichier(choisi);
  };

  const previsualiser = async () => {
    if (!fichier) return;
    setErreur('');
    setResultat(null);
    setEnCours(true);
    try {
      const formData = new FormData();
      formData.append('fichier', fichier);
      const reponse = await api.post(`/api/import/${typeImport}/previsualiser`, formData);
      setPrevisualisation(reponse.data);
    } catch (err) {
      setPrevisualisation(null);
      const erreursValidation = err.response?.data?.errors?.fichier;
      setErreur(
        erreursValidation?.join(' ') || messageErreur(err, 'La prévisualisation a échoué.')
      );
    } finally {
      setEnCours(false);
    }
  };

  const importer = async () => {
    if (!fichier) return;
    setErreur('');
    setEnCours(true);
    try {
      const formData = new FormData();
      formData.append('fichier', fichier);
      const reponse = await api.post(`/api/import/${typeImport}/valider`, formData);
      setResultat(reponse.data);
      setPrevisualisation(null);
      setFichier(null);
      if (champFichierRef.current) champFichierRef.current.value = '';
    } catch (err) {
      setErreur(messageErreur(err, "L'import a échoué. Aucune ligne n'a été enregistrée."));
    } finally {
      setEnCours(false);
    }
  };

  const colonnesDonnees =
    previsualisation?.lignes?.length > 0 ? Object.keys(previsualisation.lignes[0].donnees || {}) : [];

  return (
    <div>
      <h1>Import de référentiels</h1>

      {erreur && <div className="alerte alerte-erreur">{erreur}</div>}

      <div className="carte">
        <div className="alerte alerte-info">
          L'import est atomique : si une seule ligne est en erreur, aucune ligne n'est importée.
          Corrigez le fichier puis relancez la prévisualisation.
        </div>

        <div className="barre-outils">
          <div className="champ">
            <label htmlFor="type-import">Type de données</label>
            <select id="type-import" value={typeImport} onChange={(e) => changerType(e.target.value)}>
              {TYPES_IMPORT.map((t) => (
                <option key={t.valeur} value={t.valeur}>
                  {t.libelle}
                </option>
              ))}
            </select>
          </div>
          <button type="button" className="btn btn-secondaire" onClick={telechargerModele}>
            Télécharger le modèle
          </button>
        </div>

        <div className="champ" style={{ maxWidth: 480 }}>
          <label htmlFor="fichier-import">Fichier (xlsx ou csv, 5 Mo maximum)</label>
          <input
            id="fichier-import"
            ref={champFichierRef}
            type="file"
            accept=".xlsx,.csv"
            onChange={choisirFichier}
          />
        </div>

        <div style={{ display: 'flex', gap: '0.6rem' }}>
          <button type="button" className="btn" onClick={previsualiser} disabled={!fichier || enCours}>
            {enCours && !previsualisation ? 'Analyse…' : 'Prévisualiser'}
          </button>
        </div>
      </div>

      {previsualisation && (
        <div className="carte">
          <h2>Prévisualisation</h2>
          <div className="stat-import">
            <div className="stat">
              <strong>{previsualisation.total}</strong> lignes au total
            </div>
            <div className="stat">
              <strong>{previsualisation.importables}</strong> lignes importables
            </div>
            <div className="stat" style={{ color: 'var(--danger)' }}>
              <strong>{previsualisation.erreurs}</strong> erreurs
            </div>
            <div className="stat" style={{ color: 'var(--avertissement)' }}>
              <strong>{previsualisation.avertissements}</strong> avertissements
            </div>
          </div>

          <div className="conteneur-tableau">
            <table className="tableau">
              <thead>
                <tr>
                  <th>N° ligne</th>
                  {colonnesDonnees.map((colonne) => (
                    <th key={colonne}>{colonne}</th>
                  ))}
                  <th>Erreurs / avertissements</th>
                </tr>
              </thead>
              <tbody>
                {previsualisation.lignes.map((ligne) => {
                  const aErreurs = (ligne.erreurs || []).length > 0;
                  const aAvertissements = (ligne.avertissements || []).length > 0;
                  return (
                    <tr
                      key={ligne.numero}
                      className={aErreurs ? 'ligne-erreur' : aAvertissements ? 'ligne-avertissement' : ''}
                    >
                      <td>{ligne.numero}</td>
                      {colonnesDonnees.map((colonne) => (
                        <td key={colonne}>{ligne.donnees?.[colonne] ?? '—'}</td>
                      ))}
                      <td>
                        {(aErreurs || aAvertissements) && (
                          <ul className="liste-erreurs-cellule">
                            {(ligne.erreurs || []).map((message, i) => (
                              <li key={`e${i}`} style={{ color: 'var(--danger)' }}>
                                {message}
                              </li>
                            ))}
                            {(ligne.avertissements || []).map((message, i) => (
                              <li key={`a${i}`} style={{ color: 'var(--avertissement)' }}>
                                {message}
                              </li>
                            ))}
                          </ul>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <div style={{ marginTop: '1rem', display: 'flex', gap: '0.6rem', alignItems: 'center' }}>
            <button
              type="button"
              className="btn"
              onClick={importer}
              disabled={enCours || previsualisation.erreurs !== 0}
            >
              {enCours ? 'Import en cours…' : 'Importer'}
            </button>
            {previsualisation.erreurs !== 0 && (
              <span style={{ color: 'var(--danger)', fontSize: '0.88rem' }}>
                L'import est bloqué tant que des erreurs subsistent.
              </span>
            )}
          </div>
        </div>
      )}

      {resultat && (
        <div className="carte">
          <h2>Résultat de l'import</h2>
          <div className="alerte alerte-succes">
            {resultat.message || `Import terminé : ${resultat.importes ?? resultat.total ?? ''} ligne(s) importée(s).`}
          </div>
          <button type="button" className="btn btn-secondaire" onClick={reinitialiser}>
            Nouvel import
          </button>
        </div>
      )}
    </div>
  );
}
