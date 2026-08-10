import { useCallback, useEffect, useState } from 'react';
import api, { messageErreur, telechargerBlob } from '../api';
import { useOptions } from '../components/Formulaire';
import { Modal } from '../components/Modal';

function afficherValeur(valeur) {
  if (valeur === null || valeur === undefined || valeur === '') return '—';
  if (typeof valeur === 'object') return JSON.stringify(valeur);
  if (valeur === true) return 'Oui';
  if (valeur === false) return 'Non';
  return String(valeur);
}

function DetailAudit({ entree, onFermer }) {
  const avant = entree.avant || entree.anciennes_valeurs || {};
  const apres = entree.apres || entree.nouvelles_valeurs || {};
  const cles = [...new Set([...Object.keys(avant || {}), ...Object.keys(apres || {})])];

  return (
    <Modal titre={`Détail de l'événement n° ${entree.id}`} large onFermer={onFermer}>
      <table className="tableau-detail" style={{ marginBottom: '1rem' }}>
        <tbody>
          <tr>
            <th>Date / heure</th>
            <td>{entree.created_at ? new Date(entree.created_at).toLocaleString('fr-FR') : '—'}</td>
          </tr>
          <tr>
            <th>Utilisateur</th>
            <td>{entree.utilisateur?.nom_complet || entree.user?.nom_complet || '—'}</td>
          </tr>
          <tr>
            <th>Action</th>
            <td>{entree.action || '—'}</td>
          </tr>
          <tr>
            <th>Entité</th>
            <td>
              {entree.entite_type || '—'} {entree.entite_id ? `(n° ${entree.entite_id})` : ''}
            </td>
          </tr>
          <tr>
            <th>Adresse IP</th>
            <td>{entree.ip_address || '—'}</td>
          </tr>
        </tbody>
      </table>

      {cles.length === 0 ? (
        <p style={{ color: 'var(--texte-secondaire)' }}>Aucune modification de données enregistrée.</p>
      ) : (
        <table className="tableau-detail">
          <thead>
            <tr>
              <th>Champ</th>
              <th>Avant</th>
              <th>Après</th>
            </tr>
          </thead>
          <tbody>
            {cles.map((cle) => (
              <tr key={cle}>
                <th>{cle}</th>
                <td className="valeur-avant">{afficherValeur(avant?.[cle])}</td>
                <td className="valeur-apres">{afficherValeur(apres?.[cle])}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Modal>
  );
}

export default function Audit() {
  const [donnees, setDonnees] = useState([]);
  const [chargement, setChargement] = useState(true);
  const [erreur, setErreur] = useState('');
  const [page, setPage] = useState(1);
  const [dernierePage, setDernierePage] = useState(1);
  const [total, setTotal] = useState(0);
  const [detail, setDetail] = useState(null);

  const [filtres, setFiltres] = useState({
    user_id: '',
    action: '',
    entite_type: '',
    date_debut: '',
    date_fin: '',
  });

  const utilisateurs = useOptions('/api/utilisateurs?par_page=100', (u) => ({
    valeur: u.id,
    libelle: u.nom_complet,
  }));

  const parametresRequete = useCallback(() => {
    const p = { page };
    for (const [cle, valeur] of Object.entries(filtres)) {
      if (valeur) p[cle] = valeur;
    }
    return p;
  }, [page, filtres]);

  const charger = useCallback(async () => {
    setChargement(true);
    setErreur('');
    try {
      const reponse = await api.get('/api/audit', { params: parametresRequete() });
      setDonnees(reponse.data?.data || []);
      setDernierePage(reponse.data?.last_page || 1);
      setTotal(reponse.data?.total || 0);
    } catch (err) {
      setErreur(messageErreur(err, "Impossible de charger le journal d'audit."));
    } finally {
      setChargement(false);
    }
  }, [parametresRequete]);

  useEffect(() => {
    charger();
  }, [charger]);

  const definirFiltre = (nom, valeur) => {
    setPage(1);
    setFiltres((f) => ({ ...f, [nom]: valeur }));
  };

  const exporter = async () => {
    try {
      const { page: _page, ...filtresExport } = parametresRequete();
      await telechargerBlob('/api/audit/export', filtresExport, 'journal-audit.xlsx');
    } catch (err) {
      setErreur(messageErreur(err, "L'export a échoué."));
    }
  };

  return (
    <div>
      <h1>Journal d'audit</h1>

      {erreur && <div className="alerte alerte-erreur">{erreur}</div>}

      <div className="carte">
        <div className="barre-outils">
          <div className="champ">
            <label>Utilisateur</label>
            <select value={filtres.user_id} onChange={(e) => definirFiltre('user_id', e.target.value)}>
              <option value="">Tous</option>
              {utilisateurs.map((u) => (
                <option key={u.valeur} value={u.valeur}>
                  {u.libelle}
                </option>
              ))}
            </select>
          </div>
          <div className="champ">
            <label>Action</label>
            <input
              type="text"
              placeholder="ex. creation, modification…"
              value={filtres.action}
              onChange={(e) => definirFiltre('action', e.target.value)}
            />
          </div>
          <div className="champ">
            <label>Entité</label>
            <input
              type="text"
              placeholder="ex. Vehicule"
              value={filtres.entite_type}
              onChange={(e) => definirFiltre('entite_type', e.target.value)}
            />
          </div>
          <div className="champ">
            <label>Du</label>
            <input
              type="date"
              value={filtres.date_debut}
              onChange={(e) => definirFiltre('date_debut', e.target.value)}
            />
          </div>
          <div className="champ">
            <label>Au</label>
            <input
              type="date"
              value={filtres.date_fin}
              onChange={(e) => definirFiltre('date_fin', e.target.value)}
            />
          </div>
          <div className="espace" />
          <button type="button" className="btn btn-secondaire" onClick={exporter}>
            Exporter Excel
          </button>
        </div>

        <div className="conteneur-tableau">
          <table className="tableau">
            <thead>
              <tr>
                <th>Date / heure</th>
                <th>Utilisateur</th>
                <th>Action</th>
                <th>Entité</th>
                <th>Id</th>
                <th>Adresse IP</th>
              </tr>
            </thead>
            <tbody>
              {chargement ? (
                <tr>
                  <td className="tableau-vide" colSpan={6}>
                    Chargement…
                  </td>
                </tr>
              ) : donnees.length === 0 ? (
                <tr>
                  <td className="tableau-vide" colSpan={6}>
                    Aucun événement.
                  </td>
                </tr>
              ) : (
                donnees.map((entree) => (
                  <tr key={entree.id} className="cliquable" onClick={() => setDetail(entree)}>
                    <td>
                      {entree.created_at ? new Date(entree.created_at).toLocaleString('fr-FR') : '—'}
                    </td>
                    <td>{entree.utilisateur?.nom_complet || entree.user?.nom_complet || '—'}</td>
                    <td>{entree.action || '—'}</td>
                    <td>{entree.entite_type || '—'}</td>
                    <td>{entree.entite_id || '—'}</td>
                    <td>{entree.ip_address || '—'}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        <div className="pagination">
          <span className="info">
            {total} événement{total > 1 ? 's' : ''} — page {page} / {dernierePage}
          </span>
          <div className="espace" />
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

      {detail && <DetailAudit entree={detail} onFermer={() => setDetail(null)} />}
    </div>
  );
}
