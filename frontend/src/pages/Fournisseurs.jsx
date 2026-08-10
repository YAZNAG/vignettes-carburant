import ListePage, { BadgeActif } from '../components/ListePage';

const CHAMPS = [
  { nom: 'raison_sociale', libelle: 'Raison sociale', requis: true },
  { nom: 'identifiant_fiscal', libelle: 'Identifiant fiscal' },
  { nom: 'ice', libelle: 'ICE' },
  { nom: 'adresse', libelle: 'Adresse', large: true },
  { nom: 'ville', libelle: 'Ville' },
  { nom: 'telephone', libelle: 'Téléphone', type: 'tel' },
  { nom: 'email', libelle: 'E-mail', type: 'email' },
  { nom: 'contact', libelle: 'Contact' },
];

const COLONNES = [
  { cle: 'raison_sociale', libelle: 'Raison sociale', tri: true },
  { cle: 'identifiant_fiscal', libelle: 'Identifiant fiscal' },
  { cle: 'ice', libelle: 'ICE' },
  { cle: 'ville', libelle: 'Ville', tri: true },
  { cle: 'telephone', libelle: 'Téléphone' },
  { cle: 'email', libelle: 'E-mail' },
  { cle: 'contact', libelle: 'Contact' },
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

export default function Fournisseurs() {
  return (
    <ListePage
      titre="Fournisseurs"
      ressource="fournisseurs"
      domaine="fournisseur"
      colonnes={COLONNES}
      filtres={FILTRES}
      champs={CHAMPS}
      libelleCreation="Nouveau fournisseur"
    />
  );
}
