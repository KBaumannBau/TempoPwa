import { createBrowserRouter, Navigate, RouterProvider } from 'react-router';
import { SessionProvider } from './auth/session';
import { Layout } from './components/Layout';
import { Home } from './screens/Home';
import { Login } from './screens/Login';
import { Registration } from './screens/Registration';

// Routes as in TempoVibe (src/App.tsx), with real paths instead of hash routing.
// Screens are added chain by chain, status: docs/porting-status.md.
const router = createBrowserRouter([
  {
    element: <Layout />,
    children: [
      { path: '/', element: <Login /> },
      { path: '/registration', element: <Registration /> },
      // Interim, until the start pages per role exist
      { path: '/home', element: <Home /> },
      { path: '*', element: <Navigate to="/" replace /> },
    ],
  },
]);

export function App() {
  return (
    <SessionProvider>
      <RouterProvider router={router} />
    </SessionProvider>
  );
}
