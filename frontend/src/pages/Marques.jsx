import ListePage, { BadgeActif } from '../components/ListePage';

const CHAMPS = [{ nom: 'libelle', libelle: 'Marque', requis: true }];

const COLONNES = [
  { cle: 'libelle', libelle: 'Marque', tri: true },
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

export default function Marques() {
  return (
    <ListePage
      titre="Marques de véhicules"
      ressource="marques"
      domaine="marque"
      colonnes={COLONNES}
      filtres={FILTRES}
      champs={CHAMPS}
      libelleCreation="Nouvelle marque"
    />
  );
}
