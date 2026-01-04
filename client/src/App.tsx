import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { Login } from './pages/Login';
import { Register } from './pages/Register';
import { Home } from './pages/Home';
import { Tags } from './pages/Tags';
import { ShowBookmark } from './pages/ShowBookmark';
import { Styleguide } from './pages/Styleguide';
import { Layout } from './components/Layout/Layout';
import { IriRedirect } from './components/IriRedirect/IriRedirect';
import { isAuthenticated } from './services/auth';
import './App.css';

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
        <Route
          element={
            <ProtectedRoute>
              <Layout />
            </ProtectedRoute>
          }
        >
          <Route path="/tags" element={<Tags />} />
          <Route path="/bookmarks/:id" element={<ShowBookmark />} />
          <Route path="/" element={<Home />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export default App;
