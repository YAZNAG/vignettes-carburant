import { useEffect, useState } from 'react';
import api from '../api';
import ListePage from '../components/ListePage';

function BadgeStatutExercice({ statut }) {
  const ouvert = String(statut).toLowerCase() === 'ouvert';
  return (
    <span className={`badge ${ouvert ? 'badge-actif' : 'badge-neutre'}`}>
      {ouvert ? 'Ouvert' : 'Clôturé'}
    </span>
  );
}

const COLONNES_BASE = [
  { cle: 'annee', libelle: 'Année', tri: true },
  { cle: 'libelle', libelle: 'Libellé', tri: true },
  { cle: 'date_debut', libelle: 'Date de début', tri: true },
  { cle: 'date_fin', libelle: 'Date de fin', tri: true },
];

const FILTRES = [
  {
    nom: 'statut',
    libelle: 'Statut',
    options: [
      { valeur: 'ouvert', libelle: 'Ouvert' },
      { valeur: 'cloture', libelle: 'Clôturé' },
    ],
  },
];

const formatMontant = (v) =>
  Number(v ?? 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) + ' DH';

export default function Exercices() {
  // L'état initial se saisit par type de vignette : les champs du formulaire
  // sont générés à partir des types actifs (Vignette carburant, E-vignette, Ticket…).
  const [types, setTypes] = useState([]);

  useEffect(() => {
    api
      .get('/api/types-vignette', { params: { actif: true, par_page: 100 } })
      .then((r) => setTypes(r.data.data || []))
      .catch(() => setTypes([]));
  }, []);

  const champs = [
    { nom: 'annee', libelle: 'Année', type: 'nombre', requis: true },
    { nom: 'libelle', libelle: 'Libellé', requis: true },
    { nom: 'date_debut', libelle: 'Date de début', type: 'date', requis: true },
    { nom: 'date_fin', libelle: 'Date de fin', type: 'date', requis: true },
    ...types.map((t) => ({
      nom: `solde_${t.id}`,
      libelle: `État initial — ${t.libelle} (DH)`,
      type: 'nombre',
    })),
  ];

  const colonnes = [
    ...COLONNES_BASE,
    ...types.map((t) => ({
      cle: `solde_type_${t.id}`,
      libelle: t.libelle,
      rendu: (l) =>
        formatMontant(l.soldes?.find((s) => s.type_vignette_id === t.id)?.solde_initial ?? 0),
    })),
    { cle: 'stock_initial', libelle: 'Total initial', rendu: (l) => formatMontant(l.stock_initial) },
    { cle: 'statut', libelle: 'Statut', rendu: (l) => <BadgeStatutExercice statut={l.statut} /> },
  ];

  const preparerFormulaire = (ligne) => {
    const valeurs = {
      annee: ligne.annee,
      libelle: ligne.libelle,
      date_debut: ligne.date_debut?.slice(0, 10),
      date_fin: ligne.date_fin?.slice(0, 10),
    };
    for (const t of types) {
      valeurs[`solde_${t.id}`] =
        ligne.soldes?.find((s) => s.type_vignette_id === t.id)?.solde_initial ?? 0;
    }
    return valeurs;
  };

  // Regroupe les champs solde_<id> en tableau `soldes` attendu par l'API.
  const transformerEnvoi = (corps) => {
    const soldes = [];
    for (const t of types) {
      const cle = `solde_${t.id}`;
      soldes.push({ type_vignette_id: t.id, solde_initial: Number(corps[cle] || 0) });
      delete corps[cle];
    }
    return { ...corps, soldes };
  };

  return (
    <ListePage
      titre="Exercices budgétaires"
      ressource="exercices"
      domaine="exercice"
      colonnes={colonnes}
      filtres={FILTRES}
      champs={champs}
      preparerFormulaire={preparerFormulaire}
      transformerEnvoi={transformerEnvoi}
      sansDesactivation
      libelleCreation="Nouvel exercice"
    />
  );
}
