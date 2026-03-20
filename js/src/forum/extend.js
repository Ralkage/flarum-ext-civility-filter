import Extend from 'flarum/common/extenders';
import CivilityFlaggedNotification from './components/CivilityFlaggedNotification';

export default [
  new Extend.Notification()
    .add('civilityFlagged', CivilityFlaggedNotification),
];
