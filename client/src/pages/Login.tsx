import { useState, type FormEvent, useEffect } from 'react';
import { useNavigate, Link, useLocation } from 'react-router-dom';
import { ErrorAlert } from '../components/ErrorAlert/ErrorAlert';
import { login, ApiError } from '../services/api';
import { setToken, isAuthenticated, getBaseUrl } from '../services/auth';

export const Login = () => {

  const navigate = useNavigate();
  const location = useLocation();
  const [instanceUrl, setInstanceUrl] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [errorStatus, setErrorStatus] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [checkingAuth, setCheckingAuth] = useState(true);

  // Check for registration success message
  useEffect(() => {
    if (location.state && typeof location.state === 'object' && 'message' in location.state) {
      setError(null);
      setErrorStatus(null);
    }
  }, [location.state]);

  useEffect(() => {
    const checkAuthAndLoadInstance = async () => {
      if (isAuthenticated()) {
        navigate('/me');
      } else {
        // Load stored instance URL if available
        const storedInstanceUrl = await getBaseUrl();
        if (storedInstanceUrl) {
          setInstanceUrl(storedInstanceUrl);
        }
        setCheckingAuth(false);
      }
    };
    checkAuthAndLoadInstance();
  }, [navigate]);

  if (checkingAuth) {
    return (
      <main className="d-flex align-items-center justify-content-center min-vh-100">
        <div className="spinner-border" role="status">
          <span className="visually-hidden">Loading...</span>
        </div>
      </main>
    );
  }

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    setErrorStatus(null);
    setIsLoading(true);

    try {
      // Validate instance URL
      if (!instanceUrl.trim()) {
        setError('Please enter an instance URL');
        return;
      }

      // Normalize instance URL (remove trailing slash)
      const normalizedUrl = instanceUrl.trim().replace(/\/$/, '');

      const token = await login(normalizedUrl, username, password);
      setToken(token);
      navigate('/me');
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Something went wrong. Please try again.';
      const status = err instanceof ApiError ? err.status : null;
      setError(message);
      setErrorStatus(status);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <main className="d-flex align-items-center justify-content-center min-vh-100">
      <div className="container">
        <div className="row justify-content-center">
          <div className="col-12 col-md-6 col-lg-4">
            <div className="card shadow">
              <div className="card-body p-4">
                <div className="text-center mb-4">
                  <h1 className="h3 mb-1 fw-bold">BookmarkHive</h1>
                  <p className="text-muted mb-0">Sign in to your account</p>
                </div>

                {location.state && typeof location.state === 'object' && 'message' in location.state && (
                  <div className="alert alert-success" role="alert">
                    {location.state.message}
                  </div>
                )}

                <ErrorAlert error={error} statusCode={errorStatus} />

                <form onSubmit={handleSubmit}>
                  <div className="mb-3">
                    <label htmlFor="instanceUrl" className="form-label">
                      Instance URL
                    </label>
                    <input
                      type="url"
                      className="form-control"
                      id="instanceUrl"
                      value={instanceUrl}
                      onChange={(e) => setInstanceUrl(e.target.value)}
                      required
                      disabled={isLoading}
                      autoFocus
                    />
                    <small className="form-text text-muted">Enter your BookmarkHive instance URL</small>
                  </div>
                  <div className="mb-3">
                    <label htmlFor="username" className="form-label">
                      Username
                    </label>
                    <input
                      type="text"
                      className="form-control"
                      id="username"
                      value={username}
                      onChange={(e) => setUsername(e.target.value)}
                      required
                      disabled={isLoading}
                    />
                  </div>
                  <div className="mb-3">
                    <label htmlFor="password" className="form-label">
                      Password
                    </label>
                    <input
                      type="password"
                      className="form-control"
                      id="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      required
                      disabled={isLoading}
                    />
                  </div>
                  <button type="submit" className="btn btn-primary w-100" disabled={isLoading}>
                    {isLoading ? (
                      <>
                        <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Logging in...
                      </>
                    ) : (
                      'Login'
                    )}
                  </button>
                </form>

                <div className="text-center mt-3">
                  <p className="mb-0">
                    Don't have an account? <Link to="/register">Sign up</Link>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  );
};
