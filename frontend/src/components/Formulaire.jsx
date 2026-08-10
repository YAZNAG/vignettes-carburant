import { useEffect, useState } from 'react';
import api from '../api';

/**
 * Charge des options depuis l'API (réponse paginée Laravel {data:[...]} ou tableau brut).
 * `source` : URL, ex. /api/services?actif=true&par_page=100
 */
export function useOptions(source, mapper) {
  const [options, setOptions] = useState([]);

  useEffect(() => {
    if (!source) return;
    let actif = true;
    (async () => {
      try {
        const reponse = await api.get(source);
        const lignes = Array.isArray(reponse.data) ? reponse.data : reponse.data?.data || [];
        if (actif) {
          setOptions(
            lignes.map(
              mapper ||
                ((l) => ({
                  valeur: l.id,
                  libelle: l.nom_complet || l.libelle || l.raison_sociale || String(l.id),
                }))
            )
          );
        }
      } catch {
        if (actif) setOptions([]);
      }
    })();
    return () => {
      actif = false;
    };
  }, [source]); // eslint-disable-line react-hooks/exhaustive-deps

  return options;
}

function ChampSelect({ champ, valeur, onChange }) {
  const optionsDistantes = useOptions(champ.source, champ.mapper);
  const options = champ.source ? optionsDistantes : champ.options || [];
  return (
    <select value={valeur ?? ''} onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}>
      <option value="">{champ.videLibelle || '— Aucun —'}</option>
      {options.map((o) => (
        <option key={o.valeur} value={o.valeur}>
          {o.libelle}
        </option>
      ))}
    </select>
  );
}

/**
 * Rend une grille de champs de formulaire.
 * champ : { nom, libelle, type, requis, large, aide, options[{valeur,libelle}], source, mapper }
 * types : texte (défaut), nombre, date, email, tel, password, textarea, select, case
 */
export function FormulaireChamps({ champs, valeurs, setValeurs, erreurs = {} }) {
  const definir = (nom, valeur) => setValeurs((v) => ({ ...v, [nom]: valeur }));

  return (
    <div className="grille-formulaire">
      {champs.map((champ) => {
        const valeur = valeurs[champ.nom];
        const messagesErreur = erreurs[champ.nom];
        const classeChamp = `champ${champ.large || champ.type === 'textarea' ? ' champ-large' : ''}`;

        if (champ.type === 'case') {
          return (
            <div key={champ.nom} className={classeChamp}>
              <div className="champ-case">
                <input
                  id={`champ-${champ.nom}`}
                  type="checkbox"
                  checked={!!valeur}
                  onChange={(e) => definir(champ.nom, e.target.checked)}
                />
                <label htmlFor={`champ-${champ.nom}`}>{champ.libelle}</label>
              </div>
              {champ.aide && <div className="champ-aide">{champ.aide}</div>}
              {messagesErreur && <div className="champ-erreur">{messagesErreur.join(' ')}</div>}
            </div>
          );
        }

        return (
          <div key={champ.nom} className={classeChamp}>
            <label htmlFor={`champ-${champ.nom}`} className={champ.requis ? 'obligatoire' : ''}>
              {champ.libelle}
            </label>
            {champ.type === 'select' ? (
              <ChampSelect champ={champ} valeur={valeur} onChange={(v) => definir(champ.nom, v)} />
            ) : champ.type === 'textarea' ? (
              <textarea
                id={`champ-${champ.nom}`}
                rows={3}
                value={valeur ?? ''}
                onChange={(e) => definir(champ.nom, e.target.value)}
              />
            ) : (
              <input
                id={`champ-${champ.nom}`}
                type={champ.type === 'nombre' ? 'number' : champ.type || 'text'}
                step={champ.type === 'nombre' ? 'any' : undefined}
                value={valeur ?? ''}
                onChange={(e) => definir(champ.nom, e.target.value)}
              />
            )}
            {champ.aide && <div className="champ-aide">{champ.aide}</div>}
            {messagesErreur && <div className="champ-erreur">{messagesErreur.join(' ')}</div>}
          </div>
        );
      })}
    </div>
  );
}
