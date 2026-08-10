import ListePage, { BadgeActif } from '../components/ListePage';

const SOURCE_SERVICES = '/api/services?actif=true&par_page=100';
const SOURCE_SITES = '/api/sites?actif=true&par_page=100';

const CHAMPS = [
  { nom: 'matricule', libelle: 'Matricule', requis: true },
  { nom: 'nom', libelle: 'Nom', requis: true },
  { nom: 'prenom', libelle: 'Prénom', requis: true },
  { nom: 'fonction', libelle: 'Fonction' },
  { nom: 'service_id', libelle: 'Service', type: 'select', source: SOURCE_SERVICES },
  { nom: 'site_id', libelle: 'Site', type: 'select', source: SOURCE_SITES },
  { nom: 'telephone', libelle: 'Téléphone', type: 'tel' },
];

const COLONNES = [
  { cle: 'matricule', libelle: 'Matricule', tri: true },
  { cle: 'nom_complet', libelle: 'Nom complet', tri: 'nom' },
  { cle: 'fonction', libelle: 'Fonction', tri: true },
  { cle: 'service', libelle: 'Service', rendu: (l) => l.service?.libelle ?? '—' },
  { cle: 'site', libelle: 'Site', rendu: (l) => l.site?.libelle ?? '—' },
  { cle: 'telephone', libelle: 'Téléphone' },
  { cle: 'actif', libelle: 'État', rendu: (l) => <BadgeActif actif={l.actif !== false} /> },
];

const FILTRES = [
  { nom: 'service_id', libelle: 'Service', source: SOURCE_SERVICES },
  { nom: 'site_id', libelle: 'Site', source: SOURCE_SITES },
  {
    nom: 'actif',
    libelle: 'État',
    options: [
      { valeur: 'true', libelle: 'Actif' },
      { valeur: 'false', libelle: 'Inactif' },
    ],
  },
];

export default function Beneficiaires() {
  return (
    <ListePage
      titre="Bénéficiaires"
      ressource="beneficiaires"
      domaine="beneficiaire"
      colonnes={COLONNES}
      filtres={FILTRES}
      champs={CHAMPS}
      similarite
      libelleCreation="Nouveau bénéficiaire"
      preparerFormulaire={(l) => ({
        matricule: l.matricule,
        nom: l.nom,
        prenom: l.prenom,
        fonction: l.fonction,
        service_id: l.service_id ?? l.service?.id ?? null,
        site_id: l.site_id ?? l.site?.id ?? null,
        telephone: l.telephone,
      })}
    />
  );
}
