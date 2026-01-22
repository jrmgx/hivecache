import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Login } from './pages/Login';
import { Register } from './pages/Register';
import { Home } from './pages/Home';
import { Timeline } from './pages/Timeline';
import { SocialTag } from './pages/SocialTag';
import { InstanceBookmarks } from './pages/InstanceBookmarks';
import { Tags } from './pages/Tags';
import { ShowBookmark } from './pages/ShowBookmark';
import { PublicHome } from './pages/PublicHome';
import { PublicTags } from './pages/PublicTags';
import { PublicShowBookmark } from './pages/PublicShowBookmark';
import { Styleguide } from './pages/Styleguide';
import { Layout } from './components/Layout/Layout';
import { IriRedirect } from './components/IriRedirect/IriRedirect';
import { isAuthenticated } from './services/auth';
import { forceReindex } from './services/bookmarkIndex';
import './App.css';

// Expose forceReindex globally for console access
declare global {
  interface Window {
    forceReindex: typeof forceReindex;
  }
}

window.forceReindex = forceReindex;

const ProtectedRoute = ({ children }: { children: React.ReactNode }) => {
  if (!isAuthenticated()) {
    return <Navigate to="/login" replace />;
  }
  return <>{children}</>;
};

function App() {
  return (
    <BrowserRouter>
      <IriRedirect />
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/styleguide" element={<Styleguide />} />
        {/* Public routes - no authentication required */}
        <Route element={<Layout />}>
          <Route path="/social/:profileIdentifier/tags" element={<PublicTags />} />
          <Route path="/social/:profileIdentifier/bookmarks/:id" element={<PublicShowBookmark />} />
          <Route path="/social/:profileIdentifier" element={<PublicHome />} />
        </Route>
        {/* Protected routes - authentication required */}
        <Route
          element={
            <ProtectedRoute>
              <Layout />
            </ProtectedRoute>
          }
        >
          <Route path="/me/tags" element={<Tags />} />
          <Route path="/me/bookmarks/:id" element={<ShowBookmark />} />
          <Route path="/social/timeline" element={<Timeline />} />
          <Route path="/social/tag/:slug" element={<SocialTag />} />
          <Route path="/social/instance/:type" element={<InstanceBookmarks />} />
          <Route path="/me" element={<Home />} />
          <Route path="/" element={<Navigate to="/me" replace />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export default App;
