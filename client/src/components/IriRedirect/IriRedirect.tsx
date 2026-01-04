import { useEffect, useRef } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { iriToRoute } from '../../utils/iri';

export const IriRedirect = () => {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const processedRef = useRef<string | null>(null);

  useEffect(() => {
    const iri = searchParams.get('iri');

    // Only process if iri exists and hasn't been processed yet
    if (iri && iri !== processedRef.current) {
      processedRef.current = iri;
      const route = iriToRoute(iri);

      if (route) {
        // Remove the iri parameter from the URL
        const newParams = new URLSearchParams(searchParams);
        newParams.delete('iri');

        // If route contains query params, merge them
        const routeUrl = new URL(route, window.location.origin);
        routeUrl.searchParams.forEach((value, key) => {
          newParams.set(key, value);
        });

        // Navigate to the translated route
        const finalPath = routeUrl.pathname + (newParams.toString() ? `?${newParams.toString()}` : '');
        navigate(finalPath, { replace: true });
      } else {
        // If IRI doesn't match any pattern, just remove the parameter
        const newParams = new URLSearchParams(searchParams);
        newParams.delete('iri');
        setSearchParams(newParams, { replace: true });
      }
    }
  }, [searchParams, navigate, setSearchParams]);

  // This component doesn't render anything
  return null;
};

