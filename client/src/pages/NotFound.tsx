import { Link } from 'react-router-dom';
import { Icon } from '../components/Icon/Icon';
import { isAuthenticated } from '../services/auth';

export const NotFound = () => {
  const homePath = isAuthenticated() ? '/me' : '/';

  return (
    <div className="text-center pt-5">
      <h2 className="mb-3">Page not found</h2>
      <p className="text-muted mb-4">
        The page you are looking for does not exist or has been moved.
      </p>
      <Link to={homePath} className="btn btn-outline-secondary">
        <Icon name="arrow-left" className="me-2" />
        Go to home
      </Link>
    </div>
  );
};
