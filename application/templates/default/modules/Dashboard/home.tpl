{% asset app.theme_url ~ '/js/lists.js', 21 %}
<article class="grid_2">
    {# Remove include to remove the popular pages side bar #}
    {% include "partials/popular_pages_sidebar.tpl" %}
    {% include "partials/social_platforms.tpl" %}
</article>
<article class="grid_7">
    <section>
        <div class="block-border">
            <div class="block-content">

                <!-- Header block -->
                <div class="block-header">Battlefield 2 - Private Stats Content Management System</div>
                <h2>A Welcome Message From the Team!</h2>
                <p>
                    <b>BF2Statistics</b> is offering a unique private stats system,
                    especially designed for large and medium-sized clans. Play together
                    with your friends against a whole army of bots in Coop mode, or host a multiserver platform where
                    players from all around the world can play with each other in Conquest mode!
                    Enjoy the wide range of mods playable for battlefield 2 and get ranked with our stats system!
                </p>
                <p><strong>Current Supported Mods:</strong></p>
                <ul class="simple-list with-icon">
                    <li><span>Special Forces</span></li>
                    <li><span>Allied Extended 2.0 (AIX 2)</span></li>
                </ul>
                <p>
                    We constantly improving the available modification support.
                    Have a look at our <a href="https://bf2statistics.com/forums/news.5/">news</a> to stay up to date.
                    You are missing a modification? <a href="https://bf2statistics.com/forums/bf2statistics-discussion.6/">Please let us now!</a>
                    We are happy about great suggestions and great mods.
                </p>
                <ul class="message warning no-margin">
                    <li>You can edit the contents of this message block to welcome users to your clan in the "application/modules/home/views/index.tpl" file!</li>
                </ul>
            </div>

            <div class="block-content no-title">
                <h2>Where to Start?</h2>
                <p>
                    Want to play on our stats system? Just follow these steps:
                </p>
                <dl class="accordion">
                    <dt class="opened"><span class="number">1</span> Install a clean version of Battlefield 2</dt>
                    <dd style="display: block;">
                        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex.</p>
                        <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
                    </dd>

                    <dt class=""><span class="number">2</span> Download and install the Gamespy Redirector</dt>
                    <dd style="display: none;">
                        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex.</p>
                        <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
                    </dd>

                    <dt><span class="number">3</span> Configure the Gamespy Redirector</dt>
                    <dd style="display: none;">
                        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex.</p>
                        <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
                    </dd>

                    <dt><span class="number">4</span> Create an account</dt>
                    <dd style="display: none;">
                        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex.</p>
                        <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
                    </dd>

                    <dt><span class="number">5</span> Play the game!</dt>
                    <dd style="display: none;">
                        <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex.</p>
                        <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
                    </dd>
                </dl>
            </div>

            <div class="block-content no-title">
                <h2>Our Global Stats</h2>
                <p>General Battlefield 2 Statistics from our system.</p>
                <div class="columns">

                    <!-- Left column -->
                    <div class="colx3-left">
                        <ul class="favorites no-margin" title="Context menu available!">
                            <li>
                                <img src="{{ app.theme_url }}/images/icons/web-app/48/Profile.png" width="48" height="48">
                                <a href="#">4,862,400<br>
                                    <small>Total Players</small>
                                </a>
                            </li>

                            <li>
                                <img src="{{ app.theme_url }}/images/icons/web-app/48/Pie-Chart.png" width="48" height="48">
                                <a href="#">4,862,400<br>
                                    <small>Total Kills of all Players</small>
                                </a>
                            </li>

                            <li>
                                <img src="{{ app.theme_url }}/images/icons/web-app/48/Load.png" width="48" height="48">
                                <a href="#">462,400<br>
                                    <small>Total Awards Earned</small>
                                </a>
                            </li>

                        </ul>
                    </div>

                    <!-- Center column -->
                    <div class="colx3-center">
                        <ul class="favorites no-margin" title="Context menu available!">
                            <li>
                                <img src="{{ app.theme_url }}/images/icons/web-app/48/Profile.png" width="48" height="48">
                                <a href="#">256,400<br>
                                    <small>Total Rounds Played</small>
                                </a>
                            </li>

                            <li>
                                <img src="{{ app.theme_url }}/images/icons/web-app/48/Pie-Chart.png" width="48" height="48">
                                <a href="#">4,862,400<br>
                                    <small>Total Score of all Players</small>
                                </a>
                            </li>

                            <li>
                                <img src="{{ app.theme_url }}/images/icons/web-app/48/Load.png" width="48" height="48">
                                <a href="#">4,862,400<br>
                                    <small>Total Hours Played</small>
                                </a>
                            </li>

                        </ul>
                    </div>

                    <!-- Right column -->
                    <div class="colx3-right">
                        <ul class="favorites no-margin" title="Context menu available!">
                            <li>
                                <img src="{{ app.theme_url }}/images/icons/web-app/48/Profile.png" width="48" height="48">
                                <a href="#">25,400<br>
                                    <small>Total Flags Captured</small>
                                </a>
                            </li>

                            <li>
                                <img src="{{ app.theme_url }}/images/icons/web-app/48/Pie-Chart.png" width="48" height="48">
                                <a href="#">862,400<br>
                                    <small>Total Flags Neutralized</small>
                                </a>
                            </li>

                            <li>
                                <img src="{{ app.theme_url }}/images/icons/web-app/48/Load.png" width="48" height="48">
                                <a href="#">4,862,400<br>
                                    <small>Total Heals/Revives/Resupplies</small>
                                </a>
                            </li>
                        </ul>
                    </div>

                </div>

            </div>

            <div class="block-content no-title clearfix dark-bg">
                <img src="{{ app.theme_url }}/images/logo.png" class="float-left" style="margin: 0 1.667em 0 0; border: 1px solid #666">
                <p style="padding-top: 5px"><span class="grey">Created by</span><br>
                    <b>Wilson212</b></p>
            </div>

        </div>
    </section>

</article>

<!-- Servers -->
<article class="grid_3">
    <!-- Featured Servers -->
    {% run 'featured-servers' with { limit: 3 } %}

    <!-- Players -->
    {% run 'top-players-score' with { limit: 10 } %}
</article>