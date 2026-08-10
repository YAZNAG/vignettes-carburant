import ListePage, { BadgeActif } from '../components/ListePage';

const CHAMPS = [
  { nom: 'libelle', libelle: 'Libellé', requis: true },
  { nom: 'code', libelle: 'Code', requis: true },
  { nom: 'responsable', libelle: 'Responsable' },
];

const COLONNES = [
  { cle: 'libelle', libelle: 'Libellé', tri: true },
  { cle: 'code', libelle: 'Code', tri: true },
  { cle: 'responsable', libelle: 'Responsable' },
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

export default function Services() {
  return (
    <ListePage
      titre="Services"
      ressource="services"
      domaine="service"
      colonnes={COLONNES}
      filtres={FILTRES}
      champs={CHAMPS}
      libelleCreation="Nouveau service"
    />
  );
}
