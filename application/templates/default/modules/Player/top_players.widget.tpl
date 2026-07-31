{% asset 'public/js/libs/datatables/jquery.dataTables.js', 20 %}
{% asset app.theme_url ~ '/js/modules/player/top_players.js', 40 %}
<section>
    <div class="block-border">
        <div class="block-content">
            <div class="no-margin">
                <h1>Leaderboard</h1>
                <!-- Add the class 'table' -->
                <table id="top-players" class="table" cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        <th scope="col">
                            <!-- Table sorting arrows -->
                            <span class="column-sort">
                                <a href="#" title="Sort up" class="sort-up"></a>
                                <a href="#" title="Sort down" class="sort-down"></a>
                            </span>
                            Rank
                        </th>
                        <th scope="col">
                            <span class="column-sort">
                                <a href="#" title="Sort up" class="sort-up"></a>
                                <a href="#" title="Sort down" class="sort-down"></a>
                            </span>
                            Name
                        </th>
                        <th scope="col">
                            <span class="column-sort">
                                <a href="#" title="Sort up" class="sort-up"></a>
                                <a href="#" title="Sort down" class="sort-down"></a>
                            </span>
                            Global Score
                        </th>
                        <th scope="col" class="table-actions">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    {% foreach player in players %}
                        <tr>
                            <td style="text-align: center;" data-sort="{{ player.rank_id }}"><img src="public/images/ranks/rank_{{ player.rank_id }}.gif"/></td>
                            <td>{{ player.name }} <img src="public/images/icons/flags/{{ player.country }}.png" title="{{ player.country_name }}"/></td>
                            <td>{{ player.score }}</td>
                            <td class="table-actions">
                                <a href="{{ app.base_url }}/players/view/{{ player.id }}" title="View Player" class="with-tip"><img src="public/images/icons/fugue/arrow-curve-000-left.png" width="16" height="16"></a>
                                <a href="{{ app.base_url }}/players/view/{{ player.id }}" title="Add Buddy" class="with-tip"><img src="public/images/icons/fugue/cross-circle.png" width="16" height="16"></a>
                                <!-- <a href="{{ app.base_url }}/players/view/{id}" title="Delete Buddy" class="with-tip"><img src="public/images/icons/fugue/plus-circle.png" width="16" height="16"></a> -->
                            </td>
                        </tr>
                    {% else %}
                        <tr><td colspan="4">No players found.</td></tr>
                    {% endforeach %}
                    </tbody>
                    <tfoot></tfoot>
                </table>
            </div>
        </div>
    </div>
</section>