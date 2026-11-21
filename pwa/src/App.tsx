import { createBrowserRouter, Navigate, RouterProvider } from 'react-router';
import { SessionProvider } from './auth/session';
import { Rahmen } from './components/Rahmen';
import { Home } from './screens/Home';
import { Login } from './screens/Login';
import { Registration } from './screens/Registration';

// Routes as in TempoVibe (src/App.tsx), with real paths instead of hash routing.
// Screens are added chain by chain, status: docs/uebernahme.md.
const router = createBrowserRouter([
  {
    element: <Rahmen />,
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
