import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import { useAuth } from './AuthContext';
import Layout from './components/Layout';
import Audit from './pages/Audit';
import Beneficiaires from './pages/Beneficiaires';
import ChangerMotDePasse from './pages/ChangerMotDePasse';
import Connexion from './pages/Connexion';
import Enrolement2FA from './pages/Enrolement2FA';
import Exercices from './pages/Exercices';
import Fournisseurs from './pages/Fournisseurs';
import ImportPage from './pages/ImportPage';
import MonCompte from './pages/MonCompte';
import MotDePasseOublie from './pages/MotDePasseOublie';
import MotifsSortie from './pages/MotifsSortie';
import Parametres from './pages/Parametres';
import ReinitialiserMotDePasse from './pages/ReinitialiserMotDePasse';
import Services from './pages/Services';
import Sites from './pages/Sites';
import TableauDeBord from './pages/TableauDeBord';
import TypesVignette from './pages/TypesVignette';
import Utilisateurs from './pages/Utilisateurs';
import Marques from './pages/Marques';
import Vehicules from './pages/Vehicules';

function ExigeAuth() {
  const { utilisateur, chargement } = useAuth();
  const emplacement = useLocation();

  if (chargement) {
    return <div className="chargement">Chargement…</div>;
  }
  if (!utilisateur) {
    return <Navigate to="/connexion" replace />;
  }
  if (utilisateur.doit_changer_mdp && emplacement.pathname !== '/changer-mot-de-passe') {
    return <Navigate to="/changer-mot-de-passe" replace />;
  }
  if (
    !utilisateur.doit_changer_mdp &&
    utilisateur.totp_requis &&
    !utilisateur.totp_active &&
    emplacement.pathname !== '/enrolement-2fa'
  ) {
    return <Navigate to="/enrolement-2fa" replace />;
  }
  return <Outlet />;
}

function ExigePermission({ code, children }) {
  const { peut } = useAuth();
  if (!peut(code)) {
    return (
      <div className="carte">
        <h1>Accès refusé</h1>
        <p>Vous ne disposez pas de la permission nécessaire pour consulter cette page.</p>
      </div>
    );
  }
  return children;
}

export default function App() {
  return (
    <Routes>
      <Route path="/connexion" element={<Connexion />} />
      <Route path="/mot-de-passe-oublie" element={<MotDePasseOublie />} />
      <Route path="/reinitialiser-mot-de-passe" element={<ReinitialiserMotDePasse />} />

      <Route element={<ExigeAuth />}>
        <Route path="/changer-mot-de-passe" element={<ChangerMotDePasse />} />
        <Route path="/enrolement-2fa" element={<Enrolement2FA />} />

        <Route element={<Layout />}>
          <Route path="/" element={<TableauDeBord />} />
          <Route
            path="/vehicules"
            element={
              <ExigePermission code="vehicule.consulter">
                <Vehicules />
              </ExigePermission>
            }
          />
          <Route
            path="/marques"
            element={
              <ExigePermission code="marque.consulter">
                <Marques />
              </ExigePermission>
            }
          />
          <Route
            path="/beneficiaires"
            element={
              <ExigePermission code="beneficiaire.consulter">
                <Beneficiaires />
              </ExigePermission>
            }
          />
          <Route
            path="/types-vignette"
            element={
              <ExigePermission code="type_vignette.consulter">
                <TypesVignette />
              </ExigePermission>
            }
          />
          <Route
            path="/motifs-sortie"
            element={
              <ExigePermission code="motif_sortie.consulter">
                <MotifsSortie />
              </ExigePermission>
            }
          />
          <Route
            path="/fournisseurs"
            element={
              <ExigePermission code="fournisseur.consulter">
                <Fournisseurs />
              </ExigePermission>
            }
          />
          <Route
            path="/exercices"
            element={
              <ExigePermission code="exercice.consulter">
                <Exercices />
              </ExigePermission>
            }
          />
          <Route
            path="/services"
            element={
              <ExigePermission code="service.consulter">
                <Services />
              </ExigePermission>
            }
          />
          <Route
            path="/sites"
            element={
              <ExigePermission code="site.consulter">
                <Sites />
              </ExigePermission>
            }
          />
          <Route
            path="/import"
            element={
              <ExigePermission code="referentiel.importer">
                <ImportPage />
              </ExigePermission>
            }
          />
          <Route
            path="/utilisateurs"
            element={
              <ExigePermission code="utilisateur.consulter">
                <Utilisateurs />
              </ExigePermission>
            }
          />
          <Route
            path="/audit"
            element={
              <ExigePermission code="audit.consulter">
                <Audit />
              </ExigePermission>
            }
          />
          <Route
            path="/parametres"
            element={
              <ExigePermission code="parametre.consulter">
                <Parametres />
              </ExigePermission>
            }
          />
          <Route path="/mon-compte" element={<MonCompte />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Route>
    </Routes>
  );
}
