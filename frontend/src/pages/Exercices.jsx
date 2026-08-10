import ListePage from '../components/ListePage';

const CHAMPS = [
  { nom: 'annee', libelle: 'Année', type: 'nombre', requis: true },
  { nom: 'libelle', libelle: 'Libellé', requis: true },
  { nom: 'date_debut', libelle: 'Date de début', type: 'date', requis: true },
  { nom: 'date_fin', libelle: 'Date de fin', type: 'date', requis: true },
  { nom: 'stock_initial', libelle: 'Stock initial', type: 'nombre' },
];

function BadgeStatutExercice({ statut }) {
  const ouvert = String(statut).toLowerCase() === 'ouvert';
  return (
    <span className={`badge ${ouvert ? 'badge-actif' : 'badge-neutre'}`}>
      {ouvert ? 'Ouvert' : 'Clôturé'}
    </span>
  );
}

const COLONNES = [
  { cle: 'annee', libelle: 'Année', tri: true },
  { cle: 'libelle', libelle: 'Libellé', tri: true },
  { cle: 'date_debut', libelle: 'Date de début', tri: true },
  { cle: 'date_fin', libelle: 'Date de fin', tri: true },
  { cle: 'stock_initial', libelle: 'Stock initial' },
  { cle: 'statut', libelle: 'Statut', rendu: (l) => <BadgeStatutExercice statut={l.statut} /> },
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

export default function Exercices() {
  return (
    <ListePage
      titre="Exercices"
      ressource="exercices"
      domaine="exercice"
      colonnes={COLONNES}
      filtres={FILTRES}
      champs={CHAMPS}
      sansDesactivation
      libelleCreation="Nouvel exercice"
    />
  );
}
