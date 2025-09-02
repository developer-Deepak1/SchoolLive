import { bootstrapApplication } from '@angular/platform-browser';
import { appConfig } from './app.config';
import { AppComponent } from './app.component';

// Chart.js imports
import {
  Chart,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  Filler,
  LineController,
  BarController,
  DoughnutController
} from 'chart.js';

// Register Chart.js components
Chart.register(
  // Controllers
  LineController,
  BarController,
  DoughnutController,
  // Elements & Scales
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  // Plugins
  Title,
  Tooltip,
  Legend,
  Filler
);

bootstrapApplication(AppComponent, appConfig).catch((err) => console.error(err));
