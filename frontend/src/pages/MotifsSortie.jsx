import ListePage, { BadgeActif } from '../components/ListePage';

const CHAMPS = [
  { nom: 'libelle', libelle: 'Libellé', requis: true },
  { nom: 'code', libelle: 'Code', requis: true },
  { nom: 'description', libelle: 'Description', type: 'textarea', large: true },
  {
    nom: 'necessite_validation',
    libelle: 'Nécessite une validation',
    type: 'case',
    aide: 'Une sortie avec ce motif devra être validée avant remise des vignettes.',
  },
  {
    nom: 'soumis_plafond',
    libelle: 'Soumis au plafond',
    type: 'case',
    aide: 'Les sorties avec ce motif sont comptabilisées dans le plafond mensuel.',
  },
];

function OuiNon({ valeur }) {
  return (
    <span className={`badge ${valeur ? 'badge-bleu' : 'badge-neutre'}`}>{valeur ? 'Oui' : 'Non'}</span>
  );
}

const COLONNES = [
  { cle: 'libelle', libelle: 'Libellé', tri: true },
  { cle: 'code', libelle: 'Code', tri: true },
  { cle: 'description', libelle: 'Description' },
  {
    cle: 'necessite_validation',
    libelle: 'Validation requise',
    rendu: (l) => <OuiNon valeur={l.necessite_validation} />,
  },
  { cle: 'soumis_plafond', libelle: 'Soumis au plafond', rendu: (l) => <OuiNon valeur={l.soumis_plafond} /> },
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

export default function MotifsSortie() {
  return (
    <ListePage
      titre="Motifs de sortie"
      ressource="motifs-sortie"
      domaine="motif_sortie"
      colonnes={COLONNES}
      filtres={FILTRES}
      champs={CHAMPS}
      valeursInitiales={{ necessite_validation: false, soumis_plafond: false }}
      libelleCreation="Nouveau motif"
    />
  );
}
