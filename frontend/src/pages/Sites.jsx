import ListePage, { BadgeActif } from '../components/ListePage';

const CHAMPS = [
  { nom: 'libelle', libelle: 'Libellé', requis: true },
  { nom: 'ville', libelle: 'Ville' },
  { nom: 'region', libelle: 'Région' },
];

const COLONNES = [
  { cle: 'libelle', libelle: 'Libellé', tri: true },
  { cle: 'ville', libelle: 'Ville', tri: true },
  { cle: 'region', libelle: 'Région', tri: true },
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

export default function Sites() {
  return (
    <ListePage
      titre="Sites"
      ressource="sites"
      domaine="site"
      colonnes={COLONNES}
      filtres={FILTRES}
      champs={CHAMPS}
      libelleCreation="Nouveau site"
    />
  );
}
