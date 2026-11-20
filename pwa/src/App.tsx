import { createBrowserRouter, Navigate, RouterProvider } from 'react-router';
import { Start } from './screens/Start';

// Routen wie in TempoVibe (src/App.tsx), aber mit echten Pfaden statt Hash-Routing.
// Bildschirme kommen Kette für Kette dazu, Stand: docs/uebernahme.md.
const router = createBrowserRouter([
  { path: '/', element: <Start /> },
  { path: '*', element: <Navigate to="/" replace /> },
]);

export function App() {
  return <RouterProvider router={router} />;
}
